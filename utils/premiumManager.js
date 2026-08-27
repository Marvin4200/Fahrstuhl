/**
 * Premium Manager
 * Simple premium system (yes/no)
 */

const PremiumDatabase = require('./premiumDatabase');

class PremiumManager {
    constructor() {
        this.db = PremiumDatabase;
    }

    async initialize() {
        await this.db.init();
        console.log('✓ Premium Manager initialized');
    }

    // Check if user has premium
    async isPremium(userId) {
        return await this.db.isPremium(userId);
    }

    // Check if user has pro tier
    async isPro(userId) {
        return await this.db.isPro(userId);
    }

    // Check if command is available for user
    async isCommandAvailable(commandName, userId) {
        // Pro-only commands
        if (commandName === 'settrollmessage') {
            return await this.isPro(userId);
        }
        // Basic-premium-only commands
        if (commandName === 'notifysettings' || commandName === 'trollcolor') {
            return await this.isPremium(userId);
        }
        // All other commands are free
        return true;
    }

    // Activate premium (basic by default)
    // Default is one MONTH, not one year — the advertised price is monthly, and
    // the old 365 default silently handed out a full year to any call path that
    // omitted daysValid.
    // mode: 'extend' (default, tops up remaining time) | 'set' (absolute term)
    async activatePremium(userId, daysValid = 30, tier = 'basic', mode = 'extend') {
        await this.db.activate(userId, daysValid, tier, mode);
        const info = await this.db.getUserInfo(userId);
        return info;
    }

    // Activate pro tier
    async activatePro(userId, daysValid = 30) {
        return this.activatePremium(userId, daysValid, 'pro');
    }

    // Deactivate premium
    async deactivatePremium(userId) {
        await this.db.deactivate(userId);
    }

    // Get user info
    async getUserInfo(userId) {
        return await this.db.getUserInfo(userId);
    }

    // Get all premium users
    async getAllPremium() {
        return await this.db.getAllPremium();
    }

    // ── Guild plans ──────────────────────────────────────────────────────────

    /**
     * Effective plan for a guild.
     *
     * Checks the guild's OWN plan first, then falls back to the owner's personal
     * premium so every server that was entitled before this change stays
     * entitled. `source` says which one applied, so the dashboard can show it.
     */
    async getGuildTier(guildId, ownerId = null) {
        const plan = await this.db.getGuildPlan(guildId);
        if (plan) {
            return {
                tier: plan.tier === 'pro' ? 'pro' : 'basic',
                hasPremium: true,
                isPro: plan.tier === 'pro',
                expiresAt: plan.expires_at,
                source: 'guild',
                purchasedBy: plan.purchased_by || null,
            };
        }

        if (ownerId) {
            const ownerPremium = await this.isPremium(ownerId);
            if (ownerPremium) {
                const ownerPro = await this.isPro(ownerId);
                const info = await this.getUserInfo(ownerId);
                return {
                    tier: ownerPro ? 'pro' : 'basic',
                    hasPremium: true,
                    isPro: ownerPro,
                    expiresAt: info ? info.expires_at : null,
                    source: 'owner',
                    purchasedBy: ownerId,
                };
            }
        }

        return { tier: 'free', hasPremium: false, isPro: false, expiresAt: null, source: 'none', purchasedBy: null };
    }

    async activateGuildPlan(guildId, daysValid = 30, tier = 'basic', purchasedBy = null, mode = 'extend') {
        return await this.db.activateGuild(guildId, daysValid, tier, purchasedBy, mode);
    }

    async deactivateGuildPlan(guildId) {
        await this.db.deactivateGuild(guildId);
    }

    async getAllGuildPlans() {
        return await this.db.getAllGuildPlans();
    }

    // ── Webhook idempotency ──────────────────────────────────────────────────

    async wasEventProcessed(eventId) {
        return await this.db.wasEventProcessed(eventId);
    }

    async markEventProcessed(eventId, source = 'stripe') {
        return await this.db.markEventProcessed(eventId, source);
    }

    // ── Reminder dedup ───────────────────────────────────────────────────────

    async wasReminderSent(targetKey, expiresAt, milestone) {
        return await this.db.wasReminderSent(targetKey, expiresAt, milestone);
    }

    async markReminderSent(targetKey, expiresAt, milestone) {
        return await this.db.markReminderSent(targetKey, expiresAt, milestone);
    }

    async pruneReminderLog(olderThanDays = 120) {
        return await this.db.pruneReminderLog(olderThanDays);
    }
}

module.exports = new PremiumManager();
