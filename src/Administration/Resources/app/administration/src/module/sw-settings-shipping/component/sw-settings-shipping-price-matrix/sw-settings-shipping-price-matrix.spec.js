import { mount } from '@vue/test-utils';

/**
 * @sw-package checkout
 */
const createWrapper = async (stubOverrides = {}) => {
    return mount(
        await wrapTestComponent('sw-settings-shipping-price-matrix', {
            sync: true,
        }),
        {
            global: {
                renderStubDefaultSlot: true,
                stubs: {
                    'sw-container': true,
                    'sw-select-rule-create': true,
                    'sw-context-button': true,
                    'sw-data-grid': true,
                    'sw-context-menu-item': true,

                    'sw-price-rule-modal': true,
                    'mt-number-field': true,
                    'sw-price-field': true,
                    'sw-inheritance-switch': true,
                    'sw-inherit-wrapper': true,
                    'sw-single-select': true,
                    ...stubOverrides,
                },
            },
            props: {
                priceGroup: {
                    isNew: false,
                    ruleId: 'ruleId',
                    rule: {},
                    calculation: 1,
                    prices: [
                        {
                            _isNew: true,
                            id: 'priceId1',
                            shippingMethodId: 'shippingMethodId',
                            quantityStart: 1,
                            quantityEnds: 1,
                            ruleId: 'ruleId',
                            rule: {},
                            calculation: 1,
                            currencyPrice: [
                                {
                                    currencyId: 'euro',
                                    gross: 0,
                                    linked: false,
                                    net: 0,
                                },
                            ],
                        },
                        {
                            _isNew: true,
                            id: 'priceId2',
                            shippingMethodId: 'shippingMethodId',
                            quantityStart: 2,
                            quantityEnds: null,
                            ruleId: 'ruleId',
                            rule: {},
                            calculation: 1,
                            currencyPrice: [
                                {
                                    currencyId: 'euro',
                                    gross: 0,
                                    linked: false,
                                    net: 0,
                                },
                            ],
                        },
                    ],
                },
            },
        },
    );
};

describe('module/sw-settings-shipping/component/sw-settings-shipping-price-matrix', () => {
    beforeEach(async () => {
        Shopware.Store.get('swShippingDetail').shippingMethod = {
            id: 'shippingMethodId',
            taxType: 'auto',
            taxId: null,
            prices: [],
        };
        Shopware.Store.get('swShippingDetail').currencies = [
            { id: 'euro', isoCode: 'EUR', factor: 1, translated: { name: 'Euro' }, isSystemDefault: true },
            { id: 'dollar', isoCode: 'USD', factor: 2, translated: { name: 'Dollar' }, salesChannels: [{}] },
        ];
    });

    it('should not add conditions association', async () => {
        const wrapper = await createWrapper();
        const ruleFilterCriteria = wrapper.vm.ruleFilterCriteria;
        const shippingRuleFilterCriteria = wrapper.vm.shippingRuleFilterCriteria;

        expect(ruleFilterCriteria.hasAssociation('conditions')).toBeFalsy();
        expect(shippingRuleFilterCriteria.hasAssociation('conditions')).toBeFalsy();
    });

    // @deprecated tag:v6.8.0 - The test will be removed with the shippingRuleFilterCriteria shipping option.
    it.deprecated('v6.8.0.0')('should have price and deprecated shipping filter option', async () => {
        const wrapper = await createWrapper();
        const shippingRuleFilterCriteria = wrapper.vm.shippingRuleFilterCriteria;

        expect(shippingRuleFilterCriteria.filters[0].queries).toHaveLength(3);
        expect(shippingRuleFilterCriteria.filters[0].queries[0].value).toBe('shipping');
        expect(shippingRuleFilterCriteria.filters[0].queries[1].value).toBe('price');
        expect(shippingRuleFilterCriteria.filters[0].queries[2].value).toBeNull();
    });

    it.activeFeatureFlags(['v6.8.0.0'])('should have price filter option', async () => {
        const wrapper = await createWrapper();

        // shippingRuleFilterCriteria is deprecated and will be removed. Use ruleFilterCriteria instead
        const shippingRuleFilterCriteria = wrapper.vm.shippingRuleFilterCriteria;

        expect(shippingRuleFilterCriteria.filters[0].queries).toHaveLength(2);
        expect(shippingRuleFilterCriteria.filters[0].queries[0].value).toBe('price');
        expect(shippingRuleFilterCriteria.filters[0].queries[1].value).toBeNull();
    });

    it('should show all prices', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.showAllPrices).toBeFalsy();
        expect(wrapper.vm.prices).toHaveLength(1);

        wrapper.vm.updateShowAllPrices();

        expect(wrapper.vm.showAllPrices).toBeTruthy();
        expect(wrapper.vm.prices).toHaveLength(2);
    });

    describe('linked gross/net prices', () => {
        it('should expose the fixed tax rate so linked prices can be calculated', async () => {
            const wrapper = await createWrapper();
            const shippingMethod = Shopware.Store.get('swShippingDetail').shippingMethod;

            shippingMethod.taxType = 'fixed';
            shippingMethod.taxId = 'taxId';
            await wrapper.vm.$nextTick();

            expect(wrapper.vm.taxRateId).toBe('taxId');
        });

        it.each([
            ['auto'],
            ['highest'],
        ])('should not expose a tax rate id for the "%s" tax type', async (taxType) => {
            const wrapper = await createWrapper();
            const shippingMethod = Shopware.Store.get('swShippingDetail').shippingMethod;

            shippingMethod.taxType = taxType;
            shippingMethod.taxId = 'taxId';
            await wrapper.vm.$nextTick();

            expect(wrapper.vm.taxRateId).toBeNull();
        });

        it('should link gross and net for a newly initialised currency price', async () => {
            const wrapper = await createWrapper();
            const shippingPrice = {};

            wrapper.vm.initCurrencyPrice(shippingPrice);

            expect(shippingPrice.currencyPrice).toEqual([
                {
                    currencyId: 'euro',
                    gross: 0,
                    linked: true,
                    net: 0,
                },
            ]);
        });

        it('should keep the linked state when inheritance is removed for a currency', async () => {
            const wrapper = await createWrapper();
            const shippingPrice = wrapper.vm.priceGroup.prices[0];
            const dollar = { id: 'dollar', factor: 2 };

            wrapper.vm.setPrice(shippingPrice, dollar, { gross: 30, net: 28.04, linked: true });

            expect(shippingPrice.currencyPrice).toContainEqual({
                currencyId: 'dollar',
                gross: 30,
                net: 28.04,
                linked: true,
            });
        });

        it('should default to unlinked when the removed inheritance carries no linked state', async () => {
            const wrapper = await createWrapper();
            const shippingPrice = wrapper.vm.priceGroup.prices[0];
            const dollar = { id: 'dollar', factor: 2 };

            wrapper.vm.setPrice(shippingPrice, dollar, { gross: 30, net: 28.04 });

            expect(shippingPrice.currencyPrice).toContainEqual({
                currencyId: 'dollar',
                gross: 30,
                net: 28.04,
                linked: false,
            });
        });

        it('should carry the linked state over to converted currency prices', async () => {
            const wrapper = await createWrapper();
            const dollar = { id: 'dollar', factor: 2 };

            expect(wrapper.vm.convertPrice({ gross: 30, net: 28.04, linked: true }, dollar)).toEqual({
                currencyId: 'dollar',
                gross: 60,
                net: 56.08,
                linked: true,
            });

            expect(wrapper.vm.convertPrice({ gross: 30, net: 28.04, linked: false }, dollar)).toEqual({
                currencyId: 'dollar',
                gross: 60,
                net: 56.08,
                linked: false,
            });
        });

        it('should render a linkable price field per currency wired to the tax rate', async () => {
            const shippingMethod = Shopware.Store.get('swShippingDetail').shippingMethod;
            shippingMethod.taxType = 'fixed';
            shippingMethod.taxId = 'taxId';

            const wrapper = await createWrapper({
                'sw-data-grid': await wrapTestComponent('sw-data-grid', { sync: true }),
                'sw-inherit-wrapper': await wrapTestComponent('sw-inherit-wrapper', { sync: true }),
                'sw-price-field': await wrapTestComponent('sw-price-field', { sync: true }),
            });
            await flushPromises();

            const priceField = wrapper.findComponent('.sw-price-field');

            expect(priceField.exists()).toBe(true);
            expect(priceField.props('taxRate')).toEqual({ id: 'taxId' });
            expect(priceField.props('currency')).toMatchObject({ id: 'euro' });
            expect(priceField.props('value')).toEqual([
                {
                    currencyId: 'euro',
                    gross: 0,
                    linked: false,
                    net: 0,
                },
            ]);

            // the lock toggle is what lets a merchant link gross and net
            expect(priceField.find('.sw-price-field__lock').exists()).toBe(true);

            // the field names stay stable so existing selectors keep working
            expect(wrapper.find('[name="sw-field--priceId1-euro-gross"]').exists()).toBe(true);
            expect(wrapper.find('[name="sw-field--priceId1-euro-net"]').exists()).toBe(true);
        });
    });

    it('should add new price', async () => {
        const wrapper = await createWrapper();

        if (!wrapper.vm.shippingMethod.hasOwnProperty('prices')) {
            wrapper.vm.shippingMethod.prices = [];
        }

        const length = wrapper.vm.shippingMethod.prices.length;

        expect(wrapper.vm.showAllPrices).toBeFalsy();
        wrapper.vm.onAddNewShippingPrice();
        expect(wrapper.vm.showAllPrices).toBeTruthy();
        expect(wrapper.vm.shippingMethod.prices).toHaveLength(length + 1);
    });
});
