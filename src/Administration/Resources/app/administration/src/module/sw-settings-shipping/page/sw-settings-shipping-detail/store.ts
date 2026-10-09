/**
 * @sw-package checkout
 */

type ShippingMethodPriceInMatrix = Entity<'shipping_method_price'> & { _inNewMatrix: boolean | undefined };
type ShippingPriceGroup = {
    isNew: boolean;
    ruleId?: EntityKey<'rule'>;
    rule?: Entity<'rule'>;
    calculation?: number;
    prices: Entity<'shipping_method_price'>[];
};

const swShippingDetailStore = Shopware.Store.register({
    id: 'swShippingDetail',

    state() {
        return {
            shippingMethod: {} as Entity<'shipping_method'>,
            currencies: [] as Entity<'currency'>[],
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
                    key = 'new' as EntityKey<'rule'>;
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

        defaultCurrency(state): Entity<'currency'> | undefined {
            return state.currencies.find((currency) => currency.isSystemDefault);
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
