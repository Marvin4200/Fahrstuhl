#!/usr/bin/env node
/**
 * Cleanup Script: Löscht alle doppelten Stats-Channels
 * Behält nur den ersten Channel und löscht alle anderen
 */

const fs = require('fs');
const path = require('path');

require('dotenv').config();

const {
    Client,
    GatewayIntentBits,
    PermissionsBitField
} = require('discord.js');

const DEV_GUILD_ID = "483321401529597962";
const DEV_CATEGORY_ID = "1489213313006043310";

const client = new Client({
    intents: [GatewayIntentBits.Guilds]
});

client.once('ready', async () => {
    console.log(`✅ Logged in as ${client.user.username}`);
    
    try {
        const guild = await client.guilds.fetch(DEV_GUILD_ID);
        console.log(`📍 Guild: ${guild.name}`);
        
        // Find all stats channels
        const allStatsChannels = guild.channels.cache.filter(c =>
            c.parentId === DEV_CATEGORY_ID && c.name.startsWith("📉-stats-trolls-")
        );
        
        console.log(`\n📊 Gefundene Stats-Channels: ${allStatsChannels.size}`);
        
        if (allStatsChannels.size <= 1) {
            console.log(`✅ Nur ein oder kein Stats-Channel vorhanden. Nichts zu löschen.`);
            process.exit(0);
        }
        
        // Sort by creation date, keep the first one
        const sorted = allStatsChannels.sort((a, b) => a.createdTimestamp - b.createdTimestamp);
        const toKeep = sorted.first();
        
        console.log(`\n✅ Behalte: ${toKeep.name} (${toKeep.id}, erstellt: ${new Date(toKeep.createdTimestamp).toLocaleString()})`);
        console.log(`🗑️  Lösche ${allStatsChannels.size - 1} Duplikate:\n`);
        
        let deleted = 0;
        for (const [, channel] of sorted) {
            if (channel.id === toKeep.id) continue;
            
            try {
                console.log(`   ⏳ Lösche: ${channel.name} (${channel.id})`);
                await channel.delete("Fahrstuhl cleanup: duplicate stats channel");
                console.log(`   ✅ Gelöscht`);
                deleted++;
            } catch (err) {
                console.error(`   ❌ Fehler: ${err.message}`);
            }
        }
        
        console.log(`\n✅ Cleanup abgeschlossen: ${deleted} Channels gelöscht`);
        process.exit(0);
        
    } catch (error) {
        console.error(`❌ Fehler: ${error.message}`);
        process.exit(1);
    }
});

client.login(process.env.TOKEN);

// Timeout after 30s
setTimeout(() => {
    console.error('❌ Timeout: Bot konnte nicht verbinden');
    process.exit(1);
}, 30000);




