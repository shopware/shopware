import { mount } from '@vue/test-utils';
import state from 'src/module/sw-settings-shipping/page/sw-settings-shipping-detail/state';

Shopware.State.registerModule('swShippingDetail', state);

/**
 * @sw-package checkout
 */
const createWrapper = async (priceGroupOverride = {}) => {
    return mount(
        await wrapTestComponent('sw-settings-shipping-price-matrix', {
            sync: true,
        }),
        {
            global: {
                renderStubDefaultSlot: true,
                store: Shopware.State._store,
                stubs: {
                    'sw-card': true,
                    'sw-container': true,
                    'sw-select-rule-create': true,
                    'sw-button': true,
                    'sw-context-button': true,
                    'sw-data-grid': true,
                    'sw-context-menu-item': true,
                    'sw-alert': true,
                    'sw-price-rule-modal': true,
                    'sw-number-field': true,
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
                    ...priceGroupOverride,
                },
            },
        },
    );
};

describe('module/sw-settings-shipping/component/sw-settings-shipping-price-matrix', () => {
    beforeEach(async () => {
        Shopware.State.commit('swShippingDetail/setCurrencies', [
            { id: 'euro', translated: { name: 'Euro' }, isSystemDefault: true },
            { id: 'dollar', translated: { name: 'Dollar' } },
            { id: 'pound', translated: { name: 'Pound' } },
        ]);
    });

    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should add conditions association', async () => {
        if (Shopware.Feature.isActive('v6.7.0.0')) {
            return;
        }

        const wrapper = await createWrapper();
        const ruleFilterCriteria = wrapper.vm.ruleFilterCriteria;
        const shippingRuleFilterCriteria = wrapper.vm.shippingRuleFilterCriteria;

        expect(ruleFilterCriteria.hasAssociation('conditions')).toBeTruthy();
        expect(shippingRuleFilterCriteria.hasAssociation('conditions')).toBeTruthy();
    });

    it('should not add conditions association', async () => {
        global.activeFeatureFlags = ['v6.7.0.0'];

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

    it.each([
        [
            'cart price',
            2,
            60,
        ],
        [
            'product quantity',
            1,
            61,
        ],
    ])('should move the start of the following price when the %s end changes', async (name, calculation, expected) => {
        const wrapper = await createWrapper({
            calculation,
            prices: [
                {
                    id: 'priceId1',
                    quantityStart: 0,
                    quantityEnd: 40,
                    calculation,
                },
                {
                    id: 'priceId2',
                    quantityStart: 40,
                    quantityEnd: null,
                    calculation,
                },
            ],
        });

        const [
            firstPrice,
            secondPrice,
        ] = wrapper.vm.priceGroup.prices;

        firstPrice.quantityEnd = 60;
        wrapper.vm.onQuantityEndChange(firstPrice);

        expect(secondPrice.quantityStart).toBe(expected);
    });

    it('should not move the start of the following price when its end is emptied', async () => {
        const wrapper = await createWrapper({
            calculation: 2,
            prices: [
                {
                    id: 'priceId1',
                    quantityStart: 0,
                    quantityEnd: 40,
                    calculation: 2,
                },
                {
                    id: 'priceId2',
                    quantityStart: 40,
                    quantityEnd: null,
                    calculation: 2,
                },
            ],
        });

        const [
            firstPrice,
            secondPrice,
        ] = wrapper.vm.priceGroup.prices;

        firstPrice.quantityEnd = null;
        wrapper.vm.onQuantityEndChange(firstPrice);

        expect(secondPrice.quantityStart).toBe(40);
    });

    it('should add a new price when the end of the last price changes', async () => {
        const wrapper = await createWrapper();

        if (!wrapper.vm.shippingMethod.hasOwnProperty('prices')) {
            wrapper.vm.shippingMethod.prices = [];
        }

        const length = wrapper.vm.shippingMethod.prices.length;
        const lastPrice = wrapper.vm.priceGroup.prices[wrapper.vm.priceGroup.prices.length - 1];

        lastPrice.quantityEnd = 60;
        wrapper.vm.onQuantityEndChange(lastPrice);

        expect(wrapper.vm.shippingMethod.prices).toHaveLength(length + 1);
    });
});
