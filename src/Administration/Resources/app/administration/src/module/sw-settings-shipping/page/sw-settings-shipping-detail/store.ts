/**
 * @sw-package checkout
 */

type ShippingMethodPriceInMatrix = EntitySchema.shipping_method_price & { _inNewMatrix: boolean | undefined };
type ShippingPriceGroup = {
    isNew: boolean;
    ruleId?: string;
    rule?: EntitySchema.rule;
    calculation?: number;
    prices: EntitySchema.shipping_method_price[];
};

const swShippingDetailStore = Shopware.Store.register({
    id: 'swShippingDetail',

    state() {
        return {
            shippingMethod: {} as EntitySchema.shipping_method,
            currencies: [] as EntitySchema.currency[],
            restrictedRuleIds: [] as string[],
        };
    },

    getters: {
        shippingPriceGroups(state): Record<string, ShippingPriceGroup> {
            if (!state.shippingMethod.prices) {
                return {};
            }

            const shippingPriceGroups: Record<string, ShippingPriceGroup> = {};

            state.shippingMethod.prices.forEach((shippingPrice) => {
                let key = shippingPrice.ruleId;
                if ((shippingPrice as unknown as ShippingMethodPriceInMatrix)._inNewMatrix) {
                    key = 'new';
                }

                if (!shippingPriceGroups[key as string]) {
                    shippingPriceGroups[key as string] = {
                        isNew: key === 'new',
                        ruleId: shippingPrice.ruleId,
                        rule: shippingPrice.rule,
                        calculation: shippingPrice.calculation,
                        prices: [],
                    };
                }

                shippingPriceGroups[key as string].prices.push(shippingPrice);
            });

            return shippingPriceGroups;
        },

        defaultCurrency(state): EntitySchema.currency | undefined {
            return state.currencies.find((currency) => currency.isSystemDefault);
        },

        /** @deprecated tag:v6.7.0 - usedRules will be removed, use restrictedRuleIds instead */
        usedRules(): string[] {
            return Object.keys(this.shippingPriceGroups);
        },

        unrestrictedPriceMatrixExists(state): boolean {
            return (
                state.shippingMethod.prices?.some((shippingPrice) => {
                    return shippingPrice.ruleId === null;
                }) ?? false
            );
        },

        newPriceMatrixExists(): boolean {
            return this.shippingPriceGroups.hasOwnProperty('new');
        },
    },
});

/**
 * @private
 */
export default swShippingDetailStore;

/**
 * @private
 */
export type SwShippingDetailStore = ReturnType<typeof swShippingDetailStore>;
