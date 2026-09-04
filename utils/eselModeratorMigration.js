// Ein einziger Schalter fuer die Migration von Moderation/AutoMod/Welcome/Reaction-Roles/
// Leveling/Tickets/Temp-Voice zu EselModerator. Auf false stellen, um alle betroffenen
// Fahrstuhl-Features sofort wieder wie vorher freizuschalten -- nichts wurde geloescht,
// nur mit einem fruehen Redirect versehen.
const MIGRATED_TO_ESELMODERATOR = true;

const ESELMODERATOR_INVITE_URL =
    "https://discord.com/api/oauth2/authorize?client_id=1545456084754628658&permissions=1099798277142&scope=bot%20applications.commands";

function migrationRedirectMessage(featureLabel) {
    return {
        content: `🤖 **${featureLabel}** ist zu unserem neuen Bot **EselModerator** umgezogen.\n` +
            `Lade ihn hier ein, um weiterzumachen: ${ESELMODERATOR_INVITE_URL}`,
    };
}

module.exports = {
    MIGRATED_TO_ESELMODERATOR,
    ESELMODERATOR_INVITE_URL,
    migrationRedirectMessage,
};
