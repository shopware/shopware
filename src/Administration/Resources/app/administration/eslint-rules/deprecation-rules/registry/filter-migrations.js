function getSelectedMigrationIds() {
    return (process.env.SHOPWARE_ADMIN_DEPRECATION_IDS ?? '')
        .split(',')
        .map((id) => id.trim())
        .filter(Boolean);
}

function isMigrationSelected(migration) {
    const selectedMigrationIds = getSelectedMigrationIds();

    return selectedMigrationIds.length === 0 || selectedMigrationIds.includes(migration.id);
}

function filterMigrations(migrations) {
    return migrations.filter(isMigrationSelected);
}

module.exports = {
    filterMigrations,
    getSelectedMigrationIds,
    isMigrationSelected,
};
