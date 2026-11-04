/**
 * @sw-package framework
 */
import { defineStoreOptions, getStore, type StoreOf } from '../dependencies';
/**
 * @public
 * The id the store is registered under, for code that looks it up with `Shopware.Store.get()`.
 */
export const ruleConditionsConfigStoreId = 'ruleConditionsConfig';

/**
 * @private
 * The options the store is registered with, see `register.ts`.
 */
export const ruleConditionsConfigStoreOptions = defineStoreOptions({
    id: ruleConditionsConfigStoreId,

    state(): {
        config: null | Record<string, unknown>;
    } {
        return {
            config: null,
        };
    },

    actions: {
        getConfigForType(conditionType: string): unknown {
            if (!this.config?.[conditionType]) {
                return null;
            }

            return this.config?.[conditionType];
        },
    },
});

/**
 * @public
 */
export type RuleConditionsConfig = StoreOf<typeof ruleConditionsConfigStoreOptions>;

/**
 * @public
 * The store, looked up when it is called, so everyone who uses it shares one state.
 */
export const useRuleConditionsConfigStore = (): RuleConditionsConfig => getStore(ruleConditionsConfigStoreId);
