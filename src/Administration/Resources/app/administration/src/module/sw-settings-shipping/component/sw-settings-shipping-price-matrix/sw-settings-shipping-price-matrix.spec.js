import { mount } from '@vue/test-utils';

/**
 * @sw-package checkout
 */
const createWrapper = async () => {
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
                    'sw-inheritance-switch': true,
                    'sw-inherit-wrapper': true,
                    'sw-single-select': true,
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
        Shopware.Store.get('swShippingDetail').shippingMethod = [
            { id: 'euro', translated: { name: 'Euro' }, isSystemDefault: true },
            { id: 'dollar', translated: { name: 'Dollar' } },
            { id: 'pound', translated: { name: 'Pound' } },
        ];
    });

    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should not add conditions association', async () => {
        const wrapper = await createWrapper();
        const ruleFilterCriteria = wrapper.vm.ruleFilterCriteria;
        const shippingRuleFilterCriteria = wrapper.vm.shippingRuleFilterCriteria;

        expect(ruleFilterCriteria.hasAssociation('conditions')).toBeFalsy();
        expect(shippingRuleFilterCriteria.hasAssociation('conditions')).toBeFalsy();
    });

    it('should have price and deprecated shipping filter option', async () => {
        global.activeFeatureFlags = [];

        const wrapper = await createWrapper();
        const shippingRuleFilterCriteria = wrapper.vm.shippingRuleFilterCriteria;

        expect(shippingRuleFilterCriteria.filters[0].queries).toHaveLength(3);
        expect(shippingRuleFilterCriteria.filters[0].queries[0].value).toBe('shipping');
        expect(shippingRuleFilterCriteria.filters[0].queries[1].value).toBe('price');
        expect(shippingRuleFilterCriteria.filters[0].queries[2].value).toBeNull();
    });

    it('should have price filter option', async () => {
        global.activeFeatureFlags = ['v6.8.0.0'];

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
