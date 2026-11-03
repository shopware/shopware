/**
 * @private
 * The id the store is registered under, for code that looks it up with `Shopware.Store.get()`.
 */
export const ruleConditionsConfigStoreId = 'ruleConditionsConfig';

/**
 * @sw-package framework
 */
const ruleConditionsConfig = Shopware.Store.register({
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

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export type RuleConditionsConfig = ReturnType<typeof ruleConditionsConfig>;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default ruleConditionsConfig;
