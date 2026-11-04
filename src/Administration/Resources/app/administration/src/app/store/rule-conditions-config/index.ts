/**
 * @sw-package framework
 *
 * The `ruleConditionsConfig` Pinia store of the Administration.
 * This file is the public API, also for extensions, which reach the store as `shopware:stores/ruleConditionsConfig`.
 */
/**
 * @public
 * @export-namespace stores
 */
export { ruleConditionsConfigStoreId, useRuleConditionsConfigStore } from './src/rule-conditions-config.store';

/** @public */
export type { RuleConditionsConfig } from './src/rule-conditions-config.store';
