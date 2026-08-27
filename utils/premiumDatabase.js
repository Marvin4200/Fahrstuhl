/**
 * Premium System - SQLite Database
 * Simple premium on/off system
 */

const sqlite3 = require('sqlite3').verbose();
const path = require('path');

class PremiumDatabase {
    constructor() {
        this.dbPath = path.join(__dirname, '../data/premium.db');
        this.db = null;
    }

    async init() {
        return new Promise((resolve, reject) => {
            this.db = new sqlite3.Database(this.dbPath, (err) => {
                if (err) {
                    console.error('[PremiumDB] Failed to open database:', err);
                    reject(err);
                } else {
                    this.createTable();
                    console.log('✓ Premium database initialized');
                    resolve();
                }
            });
        });
    }

    createTable() {
        this.db.run(`
            CREATE TABLE IF NOT EXISTS premium_users (
                user_id TEXT PRIMARY KEY,
                is_premium INTEGER DEFAULT 0,
                tier TEXT DEFAULT 'basic',
                expires_at DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        `);
        // Migrate existing rows that may not have the tier column
        this.db.run(`ALTER TABLE premium_users ADD COLUMN tier TEXT DEFAULT 'basic'`, () => {});

        // Server plans live in their own table.
        //
        // Previously a "server plan" was just the guild OWNER's personal premium:
        // /premium/guild looked up guild.ownerId. That meant one purchase lit up
        // every server that person owned, an admin who wasn't the owner could
        // never buy a plan for their server at all, and transferring ownership
        // silently deleted the plan. A guild now carries its own entitlement.
        // Reminder dedup. A daily job would otherwise DM the same person every
        // single day of the notice window. The key includes expires_at, so a
        // renewal (which moves the expiry) naturally re-arms every milestone
        // without any explicit reset.
        this.db.run(`
            CREATE TABLE IF NOT EXISTS reminder_log (
                target_key TEXT NOT NULL,
                expires_at TEXT NOT NULL,
                milestone TEXT NOT NULL,
                sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (target_key, expires_at, milestone)
            )
        `);

        // Webhook idempotency: activation EXTENDS remaining time now, so applying
        // the same Stripe event twice would grant a second term for one payment.
        // Stripe retries on any non-2xx and on timeouts, so this is not exotic.
        this.db.run(`
            CREATE TABLE IF NOT EXISTS processed_events (
                event_id TEXT PRIMARY KEY,
                source TEXT DEFAULT 'stripe',
                processed_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        `);

        this.db.run(`
            CREATE TABLE IF NOT EXISTS premium_guilds (
                guild_id TEXT PRIMARY KEY,
                is_premium INTEGER DEFAULT 0,
                tier TEXT DEFAULT 'basic',
                expires_at DATETIME,
                purchased_by TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        `);
    }

    // ── Processed webhook events (idempotency) ───────────────────────────────

    /** True if this Stripe event id was already applied. */
    async wasEventProcessed(eventId) {
        return new Promise((resolve) => {
            if (!this.db || !eventId) return resolve(false);
            this.db.get(
                `SELECT event_id FROM processed_events WHERE event_id = ?`,
                [String(eventId)],
                (err, row) => resolve(!err && Boolean(row))
            );
        });
    }

    /** Record an applied event. INSERT OR IGNORE makes concurrent retries safe. */
    async markEventProcessed(eventId, source = 'stripe') {
        return new Promise((resolve) => {
            if (!this.db || !eventId) return resolve();
            this.db.run(
                `INSERT OR IGNORE INTO processed_events (event_id, source) VALUES (?, ?)`,
                [String(eventId), source],
                () => resolve()
            );
        });
    }

    // ── Reminder dedup ───────────────────────────────────────────────────────

    /** Has this exact (target, expiry, milestone) reminder already gone out? */
    async wasReminderSent(targetKey, expiresAt, milestone) {
        return new Promise((resolve) => {
            if (!this.db || !targetKey) return resolve(false);
            this.db.get(
                `SELECT 1 FROM reminder_log WHERE target_key = ? AND expires_at = ? AND milestone = ?`,
                [String(targetKey), String(expiresAt || ''), String(milestone)],
                (err, row) => resolve(!err && Boolean(row))
            );
        });
    }

    async markReminderSent(targetKey, expiresAt, milestone) {
        return new Promise((resolve) => {
            if (!this.db || !targetKey) return resolve();
            this.db.run(
                `INSERT OR IGNORE INTO reminder_log (target_key, expires_at, milestone) VALUES (?, ?, ?)`,
                [String(targetKey), String(expiresAt || ''), String(milestone)],
                () => resolve()
            );
        });
    }

    /** Housekeeping: drop rows for expiries well in the past. */
    async pruneReminderLog(olderThanDays = 120) {
        return new Promise((resolve) => {
            if (!this.db) return resolve(0);
            this.db.run(
                `DELETE FROM reminder_log
                 WHERE datetime(expires_at) < datetime('now', ?)`,
                [`-${Math.max(1, Number(olderThanDays) || 120)} days`],
                function (err) { resolve(err ? 0 : (this.changes || 0)); }
            );
        });
    }

    // ── Guild plans ──────────────────────────────────────────────────────────

    /** Active plan for a guild, or null. Expired rows are deactivated lazily. */
    async getGuildPlan(guildId) {
        return new Promise((resolve) => {
            if (!this.db) return resolve(null);
            this.db.get(
                `SELECT guild_id, is_premium, tier, expires_at, purchased_by, created_at
                 FROM premium_guilds WHERE guild_id = ?`,
                [guildId],
                (err, row) => {
                    if (err || !row || row.is_premium !== 1) return resolve(null);
                    if (row.expires_at && new Date(row.expires_at) < new Date()) {
                        this.deactivateGuild(guildId).catch(() => {});
                        return resolve(null);
                    }
                    resolve(row);
                }
            );
        });
    }

    /** Same extend-don't-reset semantics as activate() for users, incl. mode. */
    async activateGuild(guildId, daysValid = 30, tier = 'basic', purchasedBy = null, mode = 'extend') {
        const days = Math.max(1, Number(daysValid) || 30);

        return new Promise((resolve, reject) => {
            if (!this.db) return reject(new Error('Database not initialized'));

            this.db.get(
                `SELECT is_premium, tier, expires_at FROM premium_guilds WHERE guild_id = ?`,
                [guildId],
                (readErr, row) => {
                    const now = new Date();
                    let base = now;
                    let extended = false;

                    if (mode !== 'set' && !readErr && row && row.is_premium === 1 && row.expires_at) {
                        const currentExpiry = new Date(row.expires_at);
                        const sameTier = (row.tier || 'basic') === tier;
                        if (!Number.isNaN(currentExpiry.getTime()) && currentExpiry > now && sameTier) {
                            base = currentExpiry;
                            extended = true;
                        }
                    }

                    const expiresAt = new Date(base.getTime());
                    expiresAt.setDate(expiresAt.getDate() + days);

                    this.db.run(
                        `INSERT INTO premium_guilds (guild_id, is_premium, tier, expires_at, purchased_by, updated_at)
                         VALUES (?, 1, ?, ?, ?, CURRENT_TIMESTAMP)
                         ON CONFLICT(guild_id) DO UPDATE SET
                             is_premium = 1,
                             tier = excluded.tier,
                             expires_at = excluded.expires_at,
                             purchased_by = COALESCE(excluded.purchased_by, premium_guilds.purchased_by),
                             updated_at = CURRENT_TIMESTAMP`,
                        [guildId, tier, expiresAt.toISOString(), purchasedBy],
                        (err) => {
                            if (err) {
                                console.error('[PremiumDB] Failed to activate guild plan:', err.message);
                                return reject(err);
                            }
                            console.log(`[PremiumDB] ✅ Guild ${guildId} ${tier} until ${expiresAt.toISOString()} (${extended ? 'extended' : 'new'})`);
                            resolve({ expiresAt: expiresAt.toISOString(), extended, days });
                        }
                    );
                }
            );
        });
    }

    async deactivateGuild(guildId) {
        return new Promise((resolve, reject) => {
            if (!this.db) return reject(new Error('Database not initialized'));
            this.db.run(
                `UPDATE premium_guilds SET is_premium = 0, updated_at = CURRENT_TIMESTAMP WHERE guild_id = ?`,
                [guildId],
                (err) => {
                    if (err) return reject(err);
                    console.log(`[PremiumDB] ❌ Guild plan removed for ${guildId}`);
                    resolve();
                }
            );
        });
    }

    async getAllGuildPlans() {
        return new Promise((resolve) => {
            if (!this.db) return resolve([]);
            // Must filter on expiry, not just is_premium: getGuildPlan() deactivates
            // lapsed rows lazily, so without this the dashboard would show an
            // expired plan as active AND (because the guild branch short-circuits)
            // never fall back to the owner's premium for that server.
            this.db.all(
                `SELECT guild_id, tier, expires_at, purchased_by, created_at
                 FROM premium_guilds
                 WHERE is_premium = 1
                   AND (expires_at IS NULL OR datetime(expires_at) > datetime('now'))
                 ORDER BY created_at DESC`,
                (err, rows) => resolve(err ? [] : (rows || []))
            );
        });
    }

    // Check if user is premium
    async isPremium(userId) {
        return new Promise((resolve) => {
            this.db.get(
                `SELECT is_premium, expires_at FROM premium_users WHERE user_id = ?`,
                [userId],
                (err, row) => {
                    if (err || !row) {
                        resolve(false);
                        return;
                    }

                    if (row.is_premium === 1) {
                        // Check if not expired
                        if (row.expires_at && new Date(row.expires_at) < new Date()) {
                            this.deactivate(userId);
                            resolve(false);
                        } else {
                            resolve(true);
                        }
                    } else {
                        resolve(false);
                    }
                }
            );
        });
    }

    // Check if user is pro
    async isPro(userId) {
        return new Promise((resolve) => {
            this.db.get(
                `SELECT is_premium, tier, expires_at FROM premium_users WHERE user_id = ?`,
                [userId],
                (err, row) => {
                    if (err || !row) { resolve(false); return; }
                    if (row.is_premium === 1 && row.tier === 'pro') {
                        if (row.expires_at && new Date(row.expires_at) < new Date()) {
                            this.deactivate(userId);
                            resolve(false);
                        } else {
                            resolve(true);
                        }
                    } else {
                        resolve(false);
                    }
                }
            );
        });
    }

    // Activate premium for user
    //
    // Renewal semantics: if the user already has time left ON THE SAME TIER, the
    // new days are ADDED to that remaining time instead of replacing it. The old
    // version always computed expires_at from `new Date()`, so a customer with 20
    // days left who paid for another 30 ended up with 30 — silently losing the 20
    // days they had already paid for.
    //
    // On a tier CHANGE the clock restarts from now, because remaining days were
    // bought at a different price point and carrying them over either overcharges
    // (downgrade) or undercharges (upgrade).
    //
    // mode 'set' forces an absolute term (expires exactly `daysValid` days from
    // now) — that's what an admin "activate for N days" action means, as opposed
    // to a renewal that tops the existing term up.
    async activate(userId, daysValid = 30, tier = 'basic', mode = 'extend') {
        const days = Math.max(1, Number(daysValid) || 30);

        return new Promise((resolve, reject) => {
            if (!this.db) {
                console.error('[PremiumDB] Database not initialized!');
                return reject(new Error('Database not initialized'));
            }

            this.db.get(
                `SELECT is_premium, tier, expires_at FROM premium_users WHERE user_id = ?`,
                [userId],
                (readErr, row) => {
                    const now = new Date();
                    let base = now;
                    let extended = false;

                    if (mode !== 'set' && !readErr && row && row.is_premium === 1 && row.expires_at) {
                        const currentExpiry = new Date(row.expires_at);
                        const sameTier = (row.tier || 'basic') === tier;
                        if (!Number.isNaN(currentExpiry.getTime()) && currentExpiry > now && sameTier) {
                            base = currentExpiry;
                            extended = true;
                        }
                    }

                    const expiresAt = new Date(base.getTime());
                    expiresAt.setDate(expiresAt.getDate() + days);

                    console.log(
                        `[PremiumDB] ${extended ? 'Extending' : 'Activating'} ${userId} (${tier}) ` +
                        `by ${days} days, expires: ${expiresAt.toISOString()}`
                    );

                    // INSERT OR REPLACE deletes+re-inserts the whole row on a PK
                    // conflict, so any column not listed here (created_at) reverts
                    // to its schema default (CURRENT_TIMESTAMP) — every renewal of
                    // an existing premium user was silently resetting their "member
                    // since" date to today. ON CONFLICT...DO UPDATE only touches the
                    // columns named in SET, leaving created_at alone for existing rows.
                    this.db.run(
                        `INSERT INTO premium_users (user_id, is_premium, tier, expires_at, updated_at)
                         VALUES (?, 1, ?, ?, CURRENT_TIMESTAMP)
                         ON CONFLICT(user_id) DO UPDATE SET
                             is_premium = 1,
                             tier = excluded.tier,
                             expires_at = excluded.expires_at,
                             updated_at = CURRENT_TIMESTAMP`,
                        [userId, tier, expiresAt.toISOString()],
                        function(err) {
                            if (err) {
                                console.error('[PremiumDB] Failed to activate:', err.message);
                                reject(err);
                            } else {
                                console.log(`[PremiumDB] ✅ ${tier} active for ${userId} until ${expiresAt.toISOString()} - changes: ${this.changes}`);
                                resolve({ expiresAt: expiresAt.toISOString(), extended, days });
                            }
                        }
                    );
                }
            );
        });
    }

    // Deactivate premium for user
    async deactivate(userId) {
        return new Promise((resolve, reject) => {
            this.db.run(
                `UPDATE premium_users SET is_premium = 0, updated_at = CURRENT_TIMESTAMP WHERE user_id = ?`,
                [userId],
                (err) => {
                    if (err) {
                        reject(err);
                    } else {
                        console.log(`[PremiumDB] ❌ Deactivated for ${userId}`);
                        resolve();
                    }
                }
            );
        });
    }

    // Get all premium users
    async getAllPremium() {
        return new Promise((resolve) => {
            this.db.all(
                `SELECT user_id, tier, expires_at, created_at FROM premium_users WHERE is_premium = 1 ORDER BY created_at DESC`,
                (err, rows) => {
                    if (err) {
                        console.error('[PremiumDB] Failed to get all:', err);
                        resolve([]);
                    } else {
                        resolve(rows || []);
                    }
                }
            );
        });
    }

    // Get user premium info
    async getUserInfo(userId) {
        return new Promise((resolve) => {
            this.db.get(
                `SELECT user_id, is_premium, tier, expires_at, created_at FROM premium_users WHERE user_id = ?`,
                [userId],
                (err, row) => {
                    if (err || !row) {
                        resolve(null);
                    } else {
                        resolve(row);
                    }
                }
            );
        });
    }
}

module.exports = new PremiumDatabase();
