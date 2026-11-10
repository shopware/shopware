/**
 * @sw-package framework
 *
 * The codemods for the Administration APIs that Shopware 6.8 removes or changes: one rule per entry
 * of `data/v6.8/api-usage.json`, built by `utils/api-usage-rule.js`. The keys are the rule names,
 * derived from the entry ids, e.g. `api.sw-order-list.delivery-helpers` becomes
 * `api-sw-order-list-delivery-helpers`. `administration:upgrade-extensions` runs each rule as a
 * codemod of its own, see `scripts/extensionTooling/upgrade-codemods.ts`.
 */

const entries = require('./data/v6.8/api-usage.json');
const { createApiUsageRule } = require('./utils/api-usage-rule');

/**
 * The rule name of an entry.
 *
 * @example
 * apiUsageRuleName('api.sw-order-list.delivery-helpers'); // 'api-sw-order-list-delivery-helpers'
 */
function apiUsageRuleName(id) {
    return id.replaceAll('.', '-');
}

module.exports = Object.fromEntries(entries.map((entry) => [apiUsageRuleName(entry.id), createApiUsageRule(entry)]));
