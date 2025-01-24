/**
 * @sw-package framework
 */
const ruleConditionsConfig = Shopware.Store.register({
    id: 'ruleConditionsConfig',

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
