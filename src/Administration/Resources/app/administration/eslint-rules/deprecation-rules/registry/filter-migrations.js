/**
 * @sw-package framework
 */

const { getObjectOption } = require('./fix-policy');

// `deprecation:migrate --id` passes its selection to the ESLint process through this variable.
const SELECTED_IDS_VARIABLE = 'SHOPWARE_ADMIN_DEPRECATION_IDS';

/**
 * Reads the migration ids `deprecation:migrate --id` selects; empty means every migration.
 *
 * @example
 * // SHOPWARE_ADMIN_DEPRECATION_IDS="component.sw-alert, global.$tc"
 * getSelectedMigrationIds(); // ['component.sw-alert', 'global.$tc']
 */
function getSelectedMigrationIds() {
    return (process.env[SELECTED_IDS_VARIABLE] ?? '')
        .split(',')
        .map((id) => id.trim())
        .filter(Boolean);
}

/**
 * Tells whether a rule reports this migration in the current run. The `ids` option of the rule, which
 * an upgrade codemod passes, wins over the selection of `deprecation:migrate --id`. Without a selection,
 * every migration runs except the ones marked `onlyWhenSelected`.
 *
 * @example
 * isMigrationSelected({ id: 'component.sw-alert' }); // true without a selection
 */
function isMigrationSelected(migration, context = null) {
    const selectedMigrationIds = (context && getObjectOption(context).ids) ?? getSelectedMigrationIds();

    if (selectedMigrationIds.length === 0) {
        return !migration.onlyWhenSelected;
    }

    return selectedMigrationIds.includes(migration.id);
}

/**
 * Keeps the migrations a rule reports in the current run.
 *
 * @example
 * filterMigrations(registry.componentApiMigrations, context);
 */
function filterMigrations(migrations, context = null) {
    return migrations.filter((migration) => isMigrationSelected(migration, context));
}

/**
 * Flattens the selected migrations into their usage steps, each paired with its migration for the
 * report message, optionally only steps of one kind.
 *
 * @example
 * selectedUsages(registry.jsApiMigrations, 'member-call');
 * // [{ migration, usage: { kind: 'member-call', from: '…', … } }, …]
 */
function selectedUsages(migrations, kind = null, context = null) {
    return filterMigrations(migrations, context).flatMap((migration) => {
        return migration.usage
            .filter((usage) => kind === null || usage.kind === kind)
            .map((usage) => ({ migration, usage }));
    });
}

module.exports = {
    SELECTED_IDS_VARIABLE,
    filterMigrations,
    getSelectedMigrationIds,
    isMigrationSelected,
    selectedUsages,
};
