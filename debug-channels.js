#!/usr/bin/env node
require('dotenv').config();

const { Client, GatewayIntentBits } = require('discord.js');

const DEV_GUILD_ID = "483321401529597962";
const DEV_CATEGORY_ID = "1489213313006043310";

const client = new Client({
    intents: [GatewayIntentBits.Guilds, GatewayIntentBits.GuildChannels]
});

client.once('ready', async () => {
    try {
        const guild = await client.guilds.fetch(DEV_GUILD_ID);
        console.log(`\n📍 Guild: ${guild.name}\n`);
        
        // Get category
        const category = guild.channels.cache.get(DEV_CATEGORY_ID);
        console.log(`📁 Kategorie: ${category?.name || "NICHT GEFUNDEN"} (${DEV_CATEGORY_ID})`);
        
        if (!category) {
            console.log('\n❌ Kategorie nicht gefunden! Zeige alle Channels...\n');
            for (const [, ch] of guild.channels.cache) {
                console.log(`  - ${ch.name} (${ch.id}, parent: ${ch.parentId}, type: ${ch.type})`);
            }
        } else {
            console.log(`\n📊 Alle Channels in dieser Kategorie:\n`);
            const inCategory = guild.channels.cache.filter(c => c.parentId === DEV_CATEGORY_ID);
            console.log(`   Total: ${inCategory.size}\n`);
            
            for (const [, ch] of inCategory) {
                console.log(`  - ${ch.name} (${ch.id})`);
            }
        }
        
        process.exit(0);
    } catch (error) {
        console.error(`❌ Fehler: ${error.message}`);
        process.exit(1);
    }
});

client.login(process.env.TOKEN);

setTimeout(() => {
    console.error('❌ Timeout');
    process.exit(1);
}, 15000);
