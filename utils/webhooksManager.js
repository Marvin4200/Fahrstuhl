/**
 * Webhooks Manager
 * Liest Dashboard-Webhooks und triggered sie auf Events
 */

const fs = require('fs');
const path = require('path');
const axios = require('axios');

function validateWebhookUrl(rawUrl) {
    let parsed;
    try {
        parsed = new URL(rawUrl);
    } catch {
        throw new Error('Invalid webhook URL');
    }

    if (parsed.protocol !== 'https:') {
        throw new Error('Webhook URL must use https');
    }

    const host = parsed.hostname.toLowerCase();
    const blockedHosts = new Set(['localhost', '0.0.0.0']);
    if (
        blockedHosts.has(host) ||
        host.startsWith('10.') ||
        host.startsWith('192.168.') ||
        /^172\.(1[6-9]|2\d|3[01])\./.test(host) ||
        /^127\./.test(host) ||
        // 169.254.0.0/16 link-local — includes 169.254.169.254, the AWS/GCP/
        // Azure cloud metadata endpoint. Without this, a registered webhook
        // could make the bot server SSRF into its own metadata service and
        // leak instance IAM credentials.
        /^169\.254\./.test(host) ||
        host === '::1' ||
        host === '::' ||
        // IPv6 unique-local (fc00::/7) and link-local (fe80::/10)
        /^f[cd][0-9a-f]{0,2}:/.test(host) ||
        /^fe[89ab][0-9a-f]:/.test(host)
    ) {
        throw new Error('Webhook URL must not point to a private or local address');
    }

    // Note: this only validates the hostname at registration time. axios
    // re-resolves DNS on every delivery, so a hostname that resolves to a
    // public IP now but a private/metadata IP later (DNS rebinding) is not
    // caught here — closing that fully requires resolving+pinning the IP at
    // delivery time, which is a larger change left for a follow-up.
    return parsed.toString();
}

class WebhooksManager {
    constructor() {
        this.webhooksFile = path.join(__dirname, '../dashboard/data/webhooks.json');
        this.webhooks = [];
        this.loadWebhooks();
    }

    loadWebhooks() {
        try {
            if (fs.existsSync(this.webhooksFile)) {
                const data = fs.readFileSync(this.webhooksFile, 'utf8');
                this.webhooks = JSON.parse(data) || [];
                console.log(`✓ Loaded ${this.webhooks.length} webhooks`);
            }
        } catch (error) {
            console.error('Error loading webhooks:', error.message);
            this.webhooks = [];
        }
    }

    // Refresh webhooks from file
    refresh() {
        this.loadWebhooks();
    }

    /**
     * Trigger webhook for specific event
     * @param {string} eventType - Event type (e.g., "command_executed", "user_banned", "error")
     * @param {object} data - Event data to send
     */
    async trigger(eventType, data) {
        const matchingWebhooks = this.webhooks.filter(w =>
            w.active !== false &&
            w.events &&
            w.events.includes(eventType)
        );

        if (matchingWebhooks.length === 0) {
            return; // No webhooks to trigger
        }

        const payload = {
            event: eventType,
            timestamp: new Date().toISOString(),
            data: data
        };

        for (const webhook of matchingWebhooks) {
            try {
                await axios.post(webhook.url, payload, {
                    timeout: 5000,
                    headers: {
                        'Content-Type': 'application/json',
                        'User-Agent': 'Fahrstuhl-Bot/1.0'
                    }
                });
                console.log(`✓ Webhook triggered: ${webhook.name} (${eventType})`);
            } catch (error) {
                console.error(`❌ Webhook failed: ${webhook.name} - ${error.message}`);
            }
        }
    }

    /**
     * Trigger command execution webhook
     * @param {object} commandData - Command execution data
     */
    async triggerCommandExecution(commandData) {
        await this.trigger('command_executed', {
            command: commandData.commandName,
            user: commandData.userId,
            guild: commandData.guildId,
            timestamp: commandData.timestamp
        });
    }

    /**
     * Trigger error webhook
     * @param {object} errorData - Error data
     */
    async triggerError(errorData) {
        await this.trigger('error', {
            message: errorData.message,
            stack: errorData.stack,
            timestamp: errorData.timestamp
        });
    }

    /**
     * Trigger user action webhook
     * @param {object} actionData - User action data
     */
    async triggerUserAction(actionData) {
        await this.trigger('user_action', {
            action: actionData.action,
            user: actionData.userId,
            guild: actionData.guildId,
            details: actionData.details
        });
    }

    saveWebhooks() {
        try {
            fs.writeFileSync(this.webhooksFile, JSON.stringify(this.webhooks, null, 2));
        } catch (error) {
            console.error('Error saving webhooks:', error.message);
        }
    }

    add(name, url, events = []) {
        const safeUrl = validateWebhookUrl(url);
        const webhook = {
            id: Date.now().toString(),
            name: name || safeUrl,
            url: safeUrl,
            events: Array.isArray(events) ? events : [events].filter(Boolean),
            active: true,
            createdAt: new Date().toISOString()
        };
        this.webhooks.push(webhook);
        this.saveWebhooks();
        return webhook;
    }

    remove(id) {
        const before = this.webhooks.length;
        this.webhooks = this.webhooks.filter(w => w.id !== id);
        if (this.webhooks.length < before) {
            this.saveWebhooks();
            return true;
        }
        return false;
    }

    /**
     * Get all webhooks
     */
    getAllWebhooks() {
        return this.webhooks;
    }

    /**
     * Get active webhooks
     */
    getActiveWebhooks() {
        return this.webhooks.filter(w => w.active !== false);
    }
}

module.exports = new WebhooksManager();
