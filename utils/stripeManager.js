/**
 * stripeManager.js — Stripe Payment Link checkout for Fahrstuhl Premium.
 *
 * Deliberately has NO `stripe` npm dependency. With Payment Links, Stripe hosts
 * the entire checkout; all this side has to do is verify an incoming webhook and
 * activate the right tier. Signature verification is plain HMAC-SHA256, which
 * `crypto` already provides — adding the SDK would buy nothing and pull in a
 * large dependency tree on a bot that already fails to rebuild native modules.
 *
 * Flow:
 *   1. Admin creates 4 Payment Links in the Stripe dashboard (basic/pro ×
 *      monthly/lifetime) and puts their URLs in STRIPE_LINK_*.
 *   2. The pricing page appends ?client_reference_id=<discord user id> so the
 *      buyer is identified without asking them to type anything.
 *   3. Stripe POSTs checkout.session.completed to /stripe/webhook.
 *   4. handleEvent() maps the purchased price id to a tier + duration and
 *      activates premium. The bot DMs the user (via the caller's callback).
 *
 * See docs/STRIPE-SETUP.md for the setup walkthrough.
 */

const crypto = require('crypto');

/** Price id -> what it grants. Lifetime uses a very long expiry, not a flag. */
const LIFETIME_DAYS = 365 * 100;

function priceMap() {
    return {
        [process.env.STRIPE_PRICE_BASIC_MONTHLY  || '__unset_basic_monthly']:  { tier: 'basic', days: 30 },
        [process.env.STRIPE_PRICE_BASIC_LIFETIME || '__unset_basic_lifetime']: { tier: 'basic', days: LIFETIME_DAYS },
        [process.env.STRIPE_PRICE_PRO_MONTHLY    || '__unset_pro_monthly']:    { tier: 'pro',   days: 30 },
        [process.env.STRIPE_PRICE_PRO_LIFETIME   || '__unset_pro_lifetime']:   { tier: 'pro',   days: LIFETIME_DAYS },
    };
}

function isConfigured() {
    return Boolean(process.env.STRIPE_WEBHOOK_SECRET);
}

/**
 * Verify a Stripe webhook signature.
 *
 * Stripe sends `Stripe-Signature: t=<ts>,v1=<sig>[,v1=<sig>...]`, where sig is
 * HMAC-SHA256 of `<ts>.<raw body>` keyed with the endpoint secret. The RAW body
 * matters — re-serializing the parsed JSON changes the bytes and the signature
 * will never match.
 *
 * @param {Buffer|string} rawBody exact bytes Stripe sent
 * @param {string} signatureHeader value of the Stripe-Signature header
 * @param {string} secret STRIPE_WEBHOOK_SECRET (whsec_...)
 * @param {number} toleranceSeconds reject older timestamps (replay protection)
 */
function verifySignature(rawBody, signatureHeader, secret, toleranceSeconds = 300) {
    if (!rawBody || !signatureHeader || !secret) return { valid: false, reason: 'missing_input' };

    const parts = String(signatureHeader).split(',').map(p => p.trim());
    let timestamp = null;
    const signatures = [];
    for (const part of parts) {
        const idx = part.indexOf('=');
        if (idx === -1) continue;
        const key = part.slice(0, idx);
        const value = part.slice(idx + 1);
        if (key === 't') timestamp = value;
        else if (key === 'v1') signatures.push(value);
    }
    if (!timestamp || !signatures.length) return { valid: false, reason: 'malformed_header' };

    const ts = Number(timestamp);
    if (!Number.isFinite(ts)) return { valid: false, reason: 'bad_timestamp' };
    const age = Math.abs(Math.floor(Date.now() / 1000) - ts);
    if (age > toleranceSeconds) return { valid: false, reason: 'timestamp_out_of_tolerance' };

    const payload = Buffer.concat([
        Buffer.from(`${timestamp}.`, 'utf8'),
        Buffer.isBuffer(rawBody) ? rawBody : Buffer.from(rawBody, 'utf8'),
    ]);
    const expected = crypto.createHmac('sha256', secret).update(payload).digest('hex');
    const expectedBuf = Buffer.from(expected, 'utf8');

    // timingSafeEqual throws on length mismatch, so length-check first — a
    // wrong-length signature is already a definite mismatch.
    const matched = signatures.some((sig) => {
        const sigBuf = Buffer.from(sig, 'utf8');
        return sigBuf.length === expectedBuf.length && crypto.timingSafeEqual(sigBuf, expectedBuf);
    });

    return matched ? { valid: true } : { valid: false, reason: 'signature_mismatch' };
}

/** Pull the Discord user id a Payment Link carried through checkout. */
function extractDiscordUserId(session) {
    const candidates = [
        session?.client_reference_id,
        session?.metadata?.discord_user_id,
        session?.metadata?.discordUserId,
        // invoice.paid carries the subscription's metadata, not the session's
        session?.subscription_details?.metadata?.discord_user_id,
        session?.lines?.data?.[0]?.metadata?.discord_user_id,
    ];
    for (const c of candidates) {
        const value = String(c || '').trim();
        if (/^\d{17,20}$/.test(value)) return value;
    }
    return null;
}

/**
 * Resolve which tier a completed session bought.
 * Payment Links don't expand line items on the webhook payload, so we accept
 * either an expanded line_items array or an explicit metadata override.
 */
function resolvePurchase(session) {
    const map = priceMap();

    const metaTier = String(session?.metadata?.tier || '').toLowerCase();
    if (metaTier === 'basic' || metaTier === 'pro') {
        const metaDays = Number(session?.metadata?.days);
        return { tier: metaTier, days: Number.isFinite(metaDays) && metaDays > 0 ? metaDays : 30 };
    }

    // checkout.session (when line items are expanded) and invoice.paid
    // (`lines.data`) both expose the purchased price this way.
    const lineItems = session?.line_items?.data || session?.lines?.data || [];
    for (const item of lineItems) {
        const priceId = item?.price?.id || item?.plan?.id;
        if (priceId && map[priceId]) return map[priceId];

        const itemTier = String(item?.metadata?.tier || '').toLowerCase();
        if (itemTier === 'basic' || itemTier === 'pro') {
            const itemDays = Number(item?.metadata?.days);
            return { tier: itemTier, days: Number.isFinite(itemDays) && itemDays > 0 ? itemDays : 30 };
        }
    }

    // A subscription renewal with no resolvable price still has to renew
    // something — fall back to the tier the customer currently holds.
    if (session?.subscription && session?.__currentTier) {
        return { tier: session.__currentTier, days: 30 };
    }

    return null;
}

/**
 * Handle a verified Stripe event.
 *
 * @param {object} event parsed Stripe event
 * @param {object} deps
 * @param {(userId:string, days:number, tier:string) => Promise<any>} deps.activatePremium
 * @param {(userId:string, purchase:object) => Promise<void>} [deps.onActivated] notify the buyer
 * @returns {Promise<{handled:boolean, reason?:string, userId?:string, tier?:string, days?:number}>}
 */
async function handleEvent(event, deps = {}) {
    const type = event?.type;

    // Idempotency. Stripe retries on any non-2xx AND on timeouts, so the same
    // event id can arrive several times. That was harmless while activation
    // reset the expiry, but activation now EXTENDS — a redelivery would hand out
    // a second month for one payment and book the revenue twice.
    const eventId = event?.id;
    if (eventId && typeof deps.wasProcessed === 'function') {
        const seen = await Promise.resolve(deps.wasProcessed(eventId)).catch(() => false);
        if (seen) return { handled: false, reason: 'duplicate_event', duplicate: true };
    }

    // Subscription renewals arrive as invoice.paid, NOT as another checkout
    // session — without this branch a monthly Stripe subscription would bill the
    // customer forever while their premium quietly lapsed after the first term.
    const isCheckout = type === 'checkout.session.completed' || type === 'checkout.session.async_payment_succeeded';
    const isInvoice = type === 'invoice.paid' || type === 'invoice.payment_succeeded';

    if (!isCheckout && !isInvoice) {
        return { handled: false, reason: `ignored_event_type:${type}` };
    }

    const session = event?.data?.object;
    if (!session) return { handled: false, reason: 'no_session' };

    if (isInvoice) {
        // The first invoice of a subscription is already covered by the checkout
        // event; acting on both would grant the first month twice.
        if (session.billing_reason === 'subscription_create') {
            return { handled: false, reason: 'first_invoice_covered_by_checkout' };
        }
        if (session.amount_paid === 0) {
            return { handled: false, reason: 'zero_amount_invoice' };
        }
    }

    // 'paid' is the normal terminal state; 'no_payment_required' covers 100% coupons.
    const paymentStatus = session.payment_status;
    if (paymentStatus && !['paid', 'no_payment_required'].includes(paymentStatus)) {
        return { handled: false, reason: `unpaid:${paymentStatus}` };
    }

    const userId = extractDiscordUserId(session);
    if (!userId) return { handled: false, reason: 'no_discord_user_id' };

    const purchase = resolvePurchase(session);
    if (!purchase) return { handled: false, reason: 'unknown_price' };

    if (typeof deps.activatePremium !== 'function') {
        return { handled: false, reason: 'no_activator' };
    }

    await deps.activatePremium(userId, purchase.days, purchase.tier);

    if (eventId && typeof deps.markProcessed === 'function') {
        await Promise.resolve(deps.markProcessed(eventId)).catch(() => {});
    }

    if (typeof deps.onActivated === 'function') {
        await Promise.resolve(deps.onActivated(userId, { ...purchase, session })).catch(() => {});
    }

    return { handled: true, userId, tier: purchase.tier, days: purchase.days, renewal: isInvoice };
}

module.exports = {
    isConfigured,
    verifySignature,
    extractDiscordUserId,
    resolvePurchase,
    handleEvent,
    LIFETIME_DAYS,
};
