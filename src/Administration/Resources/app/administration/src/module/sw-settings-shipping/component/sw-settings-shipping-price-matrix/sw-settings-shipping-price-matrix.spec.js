import { mount } from '@vue/test-utils';

/**
 * @sw-package checkout
 */
const wrappers = [];
const createWrapper = async (stubOverrides = {}, priceGroupOverrides = {}) => {
    const wrapper = mount(
        await wrapTestComponent('sw-settings-shipping-price-matrix', {
            sync: true,
        }),
        {
            global: {
                renderStubDefaultSlot: true,
                stubs: {
                    'sw-container': true,
                    'sw-select-rule-create': {
                        props: ['ruleFilter'],
                        template: '<div />',
                    },
                    'sw-context-button': true,
                    'sw-data-grid': await wrapTestComponent('sw-data-grid', { sync: true }),
                    'sw-context-menu-item': {
                        props: ['disabled'],
                        template: '<button :disabled="disabled"><slot /></button>',
                    },

                    'sw-price-rule-modal': true,
                    'mt-number-field': true,
                    'sw-price-field': {
                        props: [
                            'value',
                            'currency',
                            'taxRate',
                            'inherited',
                        ],
                        template: '<div class="sw-price-field" />',
                    },
                    'sw-inheritance-switch': true,
                    'sw-inherit-wrapper': await wrapTestComponent('sw-inherit-wrapper', { sync: true }),
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
                            quantityEnd: 1,
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
                            quantityEnd: null,
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
                    ...priceGroupOverrides,
                },
            },
        },
    );
    wrappers.push(wrapper);
    await flushPromises();
    return wrapper;
};

describe('module/sw-settings-shipping/component/sw-settings-shipping-price-matrix', () => {
    afterEach(() => {
        wrappers.splice(0).forEach((wrapper) => wrapper.unmount());
    });

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
        const wrapper = await createWrapper(
            {},
            { calculation: null, prices: [{ id: 'rule-price', calculationRuleId: 'rule-id', currencyPrice: null }] },
        );
        const ruleFilterCriteria = wrapper
            .findComponent('.sw-settings-shipping-price-matrix__top-container-rule-select')
            .props('ruleFilter');
        const shippingRuleFilterCriteria = wrapper
            .findComponent('.sw-settings-shipping-price-matrix__column-calculation-rule-select')
            .props('ruleFilter');

        expect(ruleFilterCriteria.hasAssociation('conditions')).toBeFalsy();
        expect(shippingRuleFilterCriteria.hasAssociation('conditions')).toBeFalsy();
    });

    // @deprecated tag:v6.8.0 - The test will be removed with the shippingRuleFilterCriteria shipping option.
    it.deprecated('v6.8.0.0')('should have price and deprecated shipping filter option', async () => {
        const wrapper = await createWrapper(
            {},
            { calculation: null, prices: [{ id: 'rule-price', calculationRuleId: 'rule-id', currencyPrice: null }] },
        );
        const shippingRuleFilterCriteria = wrapper
            .findComponent('.sw-settings-shipping-price-matrix__column-calculation-rule-select')
            .props('ruleFilter');

        expect(shippingRuleFilterCriteria.filters[0].queries).toHaveLength(3);
        expect(shippingRuleFilterCriteria.filters[0].queries[0].value).toBe('shipping');
        expect(shippingRuleFilterCriteria.filters[0].queries[1].value).toBe('price');
        expect(shippingRuleFilterCriteria.filters[0].queries[2].value).toBeNull();
    });

    it.activeFeatureFlags(['v6.8.0.0'])('should have price filter option', async () => {
        const wrapper = await createWrapper(
            {},
            { calculation: null, prices: [{ id: 'rule-price', calculationRuleId: 'rule-id', currencyPrice: null }] },
        );

        // shippingRuleFilterCriteria is deprecated and will be removed. Use ruleFilterCriteria instead
        const shippingRuleFilterCriteria = wrapper
            .findComponent('.sw-settings-shipping-price-matrix__column-calculation-rule-select')
            .props('ruleFilter');

        expect(shippingRuleFilterCriteria.filters[0].queries).toHaveLength(2);
        expect(shippingRuleFilterCriteria.filters[0].queries[0].value).toBe('price');
        expect(shippingRuleFilterCriteria.filters[0].queries[1].value).toBeNull();
    });

    it('should show all prices', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.findAll('tbody .sw-data-grid__row')).toHaveLength(1);
        await wrapper.find('.sw-settings-shipping-price-matrix__price-load-all-button').trigger('click');
        expect(wrapper.findAll('tbody .sw-data-grid__row')).toHaveLength(2);
        expect(wrapper.find('.sw-settings-shipping-price-matrix__price-load-all-button').exists()).toBe(false);
    });

    describe('deleting shipping prices', () => {
        let wrapper;
        let remove;

        beforeEach(async () => {
            remove = jest.fn();
            Shopware.Store.get('swShippingDetail').shippingMethod.prices.remove = remove;
            wrapper = await createWrapper();
        });

        it.each([
            [false, 0],
            [true, 0],
            [true, 1],
        ])('deletes a rule price with expanded=%s at index %s without changing other rules', async (expanded, index) => {
            const prices = wrapper.props('priceGroup').prices.map((price, priceIndex) => ({
                ...price,
                calculation: null,
                calculationRuleId: `calculationRule${priceIndex}`,
                quantityStart: null,
                quantityEnd: null,
            }));
            await wrapper.setProps({
                priceGroup: { ...wrapper.props('priceGroup'), calculation: null, prices },
            });
            if (expanded) {
                await wrapper.find('.sw-settings-shipping-price-matrix__price-load-all-button').trigger('click');
            }
            const originalPrices = Shopware.Utils.object.cloneDeep(prices);

            expect(wrapper.findAll('tbody .sw-data-grid__row')).toHaveLength(expanded ? 2 : 1);
            await wrapper.findAll('tbody .sw-data-grid__row')[index].find('button').trigger('click');

            expect(remove).toHaveBeenCalledWith(prices[index].id);
            expect(wrapper.props('priceGroup').prices).toEqual(originalPrices);
        });

        it.each([
            [0, [[1, null]]],
            [1, [[1, null]]],
        ])('keeps quantity ranges contiguous when deleting price %s', async (index, expectedRanges) => {
            const prices = wrapper.props('priceGroup').prices;

            await wrapper.find('.sw-settings-shipping-price-matrix__price-load-all-button').trigger('click');
            await wrapper.findAll('tbody .sw-data-grid__row')[index].find('button').trigger('click');

            expect(remove).toHaveBeenCalledWith(prices[index].id);
            expect(
                prices
                    .filter((price, priceIndex) => priceIndex !== index)
                    .map((price) => [price.quantityStart, price.quantityEnd]),
            ).toEqual(expectedRanges);
        });

        it('keeps the only price in a matrix', async () => {
            await wrapper.setProps({
                priceGroup: { ...wrapper.props('priceGroup'), prices: [wrapper.props('priceGroup').prices[0]] },
            });

            expect(wrapper.find('tbody .sw-data-grid__row button').attributes('disabled')).toBeDefined();
            await wrapper.find('tbody .sw-data-grid__row button').trigger('click');

            expect(remove).not.toHaveBeenCalled();
        });
    });

    describe('linked gross/net prices', () => {
        it('should expose the fixed tax rate so linked prices can be calculated', async () => {
            const wrapper = await createWrapper();
            const shippingMethod = Shopware.Store.get('swShippingDetail').shippingMethod;

            shippingMethod.taxType = 'fixed';
            shippingMethod.taxId = 'taxId';
            await flushPromises();

            expect(wrapper.findComponent('.sw-price-field').props('taxRate')).toEqual({ id: 'taxId' });
        });

        it.each([['auto'], ['highest']])('should not expose a tax rate id for the "%s" tax type', async (taxType) => {
            const wrapper = await createWrapper();
            const shippingMethod = Shopware.Store.get('swShippingDetail').shippingMethod;

            shippingMethod.taxType = taxType;
            shippingMethod.taxId = 'taxId';
            await flushPromises();

            expect(wrapper.findComponent('.sw-price-field').props('taxRate')).toEqual({ id: null });
        });

        it('should link gross and net for a newly initialised currency price', async () => {
            const wrapper = await createWrapper();
            const shippingPrice = wrapper.props('priceGroup').prices[0];
            shippingPrice.currencyPrice = null;
            await wrapper.setProps({ priceGroup: { ...wrapper.props('priceGroup'), prices: [shippingPrice] } });
            await flushPromises();

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
            const shippingPrice = wrapper.props('priceGroup').prices[0];
            await wrapper
                .findComponent('.sw-settings-shipping-price-matrix__price-inherit-icon')
                .vm.$emit('inheritance-remove', { gross: 30, net: 28.04, linked: true });
            await flushPromises();

            expect(shippingPrice.currencyPrice).toContainEqual({
                currencyId: 'dollar',
                gross: 30,
                net: 28.04,
                linked: true,
            });
        });

        it('should default to unlinked when the removed inheritance carries no linked state', async () => {
            const wrapper = await createWrapper();
            const shippingPrice = wrapper.props('priceGroup').prices[0];
            await wrapper
                .findComponent('.sw-settings-shipping-price-matrix__price-inherit-icon')
                .vm.$emit('inheritance-remove', { gross: 30, net: 28.04 });
            await flushPromises();

            expect(shippingPrice.currencyPrice).toContainEqual({
                currencyId: 'dollar',
                gross: 30,
                net: 28.04,
                linked: false,
            });
        });

        it.each([true, false])('should carry linked=%s over to inherited currency prices', async (linked) => {
            const wrapper = await createWrapper();
            const shippingPrice = wrapper.props('priceGroup').prices[0];
            shippingPrice.currencyPrice = [{ currencyId: 'euro', gross: 30, net: 28.04, linked }];
            await wrapper.setProps({ priceGroup: { ...wrapper.props('priceGroup'), prices: [shippingPrice] } });
            await flushPromises();

            const dollarPrice = wrapper
                .findAllComponents('.sw-price-field')
                .find((field) => field.props('currency').id === 'dollar');
            expect(dollarPrice.props('value')).toEqual([
                {
                    currencyId: 'dollar',
                    gross: 60,
                    net: 56.08,
                    linked,
                },
            ]);
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

        const prices = Shopware.Store.get('swShippingDetail').shippingMethod.prices;
        const length = prices.length;

        expect(wrapper.findAll('tbody .sw-data-grid__row')).toHaveLength(1);
        await wrapper.find('.sw-settings-shipping-price-matrix__top-container-add-new-rule').trigger('click');
        expect(wrapper.find('.sw-settings-shipping-price-matrix__price-load-all-button').exists()).toBe(false);
        expect(prices).toHaveLength(length + 1);
    });
});
