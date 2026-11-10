/**
 * @sw-package framework
 */

const { appendRegistryContext } = require('../registry/format-migration-message');

/**
 * Joins the headline lines of a report with the context of its `data/v6.8` entry: the description,
 * the removal version and the `UPGRADE` sections that document the change.
 *
 * @example
 * entryMessage(['Vuex is removed in 6.8.'], entry);
 * // 'Vuex is removed in 6.8.\n<description>\nRemoved in Shopware 6.8.0.\nReferences:\nupgrade: UPGRADE-6.8.md#…'
 */
function entryMessage(lines, entry) {
    return appendRegistryContext(lines, {
        description: entry.description,
        removedIn: entry.removedIn,
        references: (entry.upgrade ?? []).map((target) => ({ type: 'upgrade', target })),
    });
}

module.exports = {
    entryMessage,
};
