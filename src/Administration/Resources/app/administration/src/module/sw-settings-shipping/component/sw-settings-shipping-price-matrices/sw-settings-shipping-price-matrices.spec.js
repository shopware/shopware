import { mount } from '@vue/test-utils';
import state from 'src/module/sw-settings-shipping/page/sw-settings-shipping-detail/state';

/**
 * @package checkout
 */

Shopware.State.registerModule('swShippingDetail', state);

describe('module/sw-settings-shipping/component/sw-settings-shipping-price-matrices', () => {
    const createWrapper = async () => {
        return mount(await wrapTestComponent('sw-settings-shipping-price-matrices', {
            sync: true,
        }), {
            global: {
                renderStubDefaultSlot: true,
                store: Shopware.State._store,
                stubs: {
                    'sw-settings-shipping-price-matrix': await wrapTestComponent('sw-settings-shipping-price-matrix', {
                        sync: true,
                    }),
                    'sw-card': true,
                    'sw-alert': true,
                    'sw-container': true,
                    'sw-select-rule-create': true,
                    'sw-single-select': true,
                    'sw-icon': true,
                    'sw-popover': true,
                    'sw-text-field': await wrapTestComponent('sw-text-field'),
                    'sw-text-field-deprecated': await wrapTestComponent('sw-text-field-deprecated', { sync: true }),
                    'sw-button': await wrapTestComponent('sw-button'),
                    'sw-button-deprecated': await wrapTestComponent('sw-button-deprecated'),
                    'sw-context-button': await wrapTestComponent('sw-context-button', {
                        sync: true,
                    }),
                    'sw-data-grid': await wrapTestComponent('sw-data-grid'),
                    'sw-number-field': {
                        template: '<input type="number" v-model="value" />',
                        props: ['value', 'size'],
                    },
                    'sw-context-menu': await wrapTestComponent('sw-context-menu'),
                    'sw-context-menu-item': await wrapTestComponent('sw-context-menu-item'),
                    'sw-checkbox-field': true,
                    'sw-data-grid-settings': true,
                    'sw-inherit-wrapper': await wrapTestComponent('sw-inherit-wrapper'),
                    'sw-inheritance-switch': await wrapTestComponent('sw-inheritance-switch', {
                        sync: true,
                    }),
                    'sw-price-rule-modal': true,
                    'router-link': true,
                    'sw-loader': true,
                    'sw-data-grid-column-boolean': true,
                    'sw-data-grid-inline-edit': true,
                    'sw-data-grid-skeleton': true,
                    'sw-help-text': true,
                },
                mocks: {
                    $te: () => false,
                },
                provide: {
                    ruleConditionDataProviderService: {
                        getRestrictedRules: () => Promise.resolve([]),
                    },
                    repositoryFactory: {
                        create: (name) => {
                            if (name === 'rule') {
                                return {
                                    search: () => Promise.resolve([]),
                                    get: () => Promise.resolve({}),
                                };
                            }

                            if (name === 'shipping_method') {
                                return {};
                            }

                            if (name === 'shipping_method_price') {
                                return {
                                    create: () => Promise.resolve([]),
                                };
                            }

                            return null;
                        },
                    },
                },
            },
        });
    };

    beforeEach(async () => {
        Shopware.State.commit('swShippingDetail/setCurrencies', [
            { id: 'euro', translated: { name: 'Euro' }, isSystemDefault: true },
            { id: 'dollar', translated: { name: 'Dollar' } },
            { id: 'pound', translated: { name: 'Pound' } },
        ]);
        Shopware.State.commit('swShippingDetail/setShippingMethod', {
            id: '12345',
            prices: [
                {
                    id: 'a1',
                    ruleId: '2',
                    quantityStart: 1,
                    quantityEnd: 20,
                    shippingMethodId: 123,
                    calculationRule: 987,
                    calculation: 1,
                    currencyPrice: [
                        {
                            currencyId: 'euro',
                            gross: 50,
                            net: 25,
                            linked: false,
                        },
                    ],
                },
                {
                    id: 'b2',
                    ruleId: '2',
                    quantityStart: 21,
                    quantityEnd: null,
                    shippingMethodId: 345,
                    calculationRule: 876,
                    calculation: 1,
                    currencyPrice: [
                        {
                            currencyId: 'euro',
                            gross: 40,
                            net: 20,
                            linked: false,
                        },
                    ],
                },
            ],
        });

        const shippingMethod = Shopware.State.get('swShippingDetail').shippingMethod;

        // add remove method to array
        shippingMethod.prices.remove = (id) => {
            shippingMethod.prices = shippingMethod.prices.filter(price => price.id !== id);
        };
    });

    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should render one shipping price matrix', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        Shopware.State.commit('swShippingDetail/setShippingMethod', {
            prices: [
                { ruleId: '1' },
                { ruleId: '1' },
            ],
        });

        await flushPromises();

        const matrices = wrapper.findAllComponents('.sw-settings-shipping-price-matrix');

        expect(matrices).toHaveLength(1);
    });

    it('should render two shipping price matrices', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await Shopware.State.commit('swShippingDetail/setShippingMethod', {
            prices: [
                { ruleId: '1' },
                { ruleId: '2' },
            ],
        });

        const matrices = wrapper.findAllComponents('.sw-settings-shipping-price-matrix');

        expect(matrices).toHaveLength(2);
    });

    it('should render five shipping price matrices', async () => {
        const wrapper = await createWrapper();
        await flushPromises();


        await Shopware.State.commit('swShippingDetail/setShippingMethod', {
            prices: [
                { ruleId: '1' },
                { ruleId: '2' },
                { ruleId: '3' },
                { ruleId: '4' },
                { ruleId: '5' },
            ],
        });

        const matrices = wrapper.findAllComponents('.sw-settings-shipping-price-matrix');

        expect(matrices).toHaveLength(5);
    });

    it('should enable the button when there are available rules', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        Shopware.State.commit('swShippingDetail/setShippingMethod', {
            prices: [
                { ruleId: '1' },
                { ruleId: '2' },
            ],
        });

        const addPriceMatrixButton = wrapper.find('.sw-settings-shipping-price-matrices__actions .sw-button');
        expect(addPriceMatrixButton.attributes('disabled')).toBeFalsy();
    });

    it('should duplicate the price matrix', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        Shopware.State.commit('swShippingDetail/setShippingMethod', {
            id: 7,
            prices: [
                {
                    ruleId: '1',
                    quantityStart: 15,
                    quantityEnd: 30,
                    shippingMethodId: 123,
                    calculationRule: 987,
                    currencyPrice: 444,
                },
                {
                    ruleId: '1',
                    quantityStart: 25,
                    quantityEnd: 35,
                    shippingMethodId: 345,
                    calculationRule: 876,
                    currencyPrice: 555,
                },
                {
                    ruleId: '1',
                    quantityStart: 45,
                    quantityEnd: 60,
                    shippingMethodId: 678,
                    calculationRule: 765,
                    currencyPrice: 666,
                },
                { ruleId: '2' },
            ],
        });

        expect(Object.keys(wrapper.vm.shippingPriceGroups)).not.toContain(' null');

        wrapper.vm.onDuplicatePriceMatrix(wrapper.vm.shippingPriceGroups['1']);

        expect(Object.keys(wrapper.vm.shippingPriceGroups)).toContain('null');
        expect(Object.keys(wrapper.vm.shippingPriceGroups)).not.toContain('new');
        expect(Object.keys(wrapper.vm.shippingPriceGroups)).not.toContain('undefined');

        const shippingPriceGroupOriginal = wrapper.vm.shippingPriceGroups['1'];
        const shippingPriceGroupDuplication = wrapper.vm.shippingPriceGroups.null;

        shippingPriceGroupOriginal.prices.forEach((price, index) => {
            const duplication = shippingPriceGroupDuplication.prices[index];

            expect(duplication.quantityStart).toEqual(price.quantityStart);
            expect(duplication.quantityEnd).toEqual(price.quantityEnd);
            expect(duplication.shippingMethodId).toEqual(price.shippingMethodId);
            expect(duplication.calculationRule).toEqual(price.calculationRule);
            expect(duplication.currencyPrice).toEqual(price.currencyPrice);
            expect(duplication.ruleId).toBeNull();
        });
    });

    it('should delete the shipping price group', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        Shopware.State.commit('swShippingDetail/setShippingMethod', {
            id: 7,
            prices: [
                { ruleId: '1' },
                { ruleId: '2' },
                { ruleId: '2' },
                { ruleId: '3' },
            ],
        });

        expect(Object.keys(wrapper.vm.shippingPriceGroups)).toContain('2');

        wrapper.vm.onDeletePriceMatrix(wrapper.vm.shippingPriceGroups['2']);

        expect(Object.keys(wrapper.vm.shippingPriceGroups)).not.toContain('2');
    });

    it('should add the shipping price group', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        Shopware.State.commit('swShippingDetail/setShippingMethod', {
            id: 7,
            prices: [
                { ruleId: '1' },
                { ruleId: '2' },
                { ruleId: '2' },
                { ruleId: '3' },
            ],
        });

        Shopware.State.get('swShippingDetail').shippingMethod.prices.add = (value) => {
            Shopware.State.get('swShippingDetail').shippingMethod.prices.push(value);
        };

        expect(Object.keys(wrapper.vm.shippingPriceGroups)).not.toContain('null');

        wrapper.vm.onAddNewPriceGroup();

        expect(Object.keys(wrapper.vm.shippingPriceGroups)).toContain('null');
    });

    it('should show all rules with matching prices', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const rowOneQuantityStart = wrapper.find('.sw-data-grid__row--0 .sw-data-grid__cell--quantityStart input');
        const rowOneQuantityEnd = wrapper.find('.sw-data-grid__row--0 .sw-data-grid__cell--quantityEnd input');
        const rowTwoQuantityStart = wrapper.find('.sw-data-grid__row--1 .sw-data-grid__cell--quantityStart input');
        const rowTwoQuantityEnd = wrapper.find('.sw-data-grid__row--1 .sw-data-grid__cell--quantityEnd input');

        expect(rowOneQuantityStart.element.value).toBe('1');
        expect(rowOneQuantityEnd.element.value).toBe('20');
        expect(rowTwoQuantityStart.element.value).toBe('21');
        expect(rowTwoQuantityEnd.element.value).toBe('');
    });

    it('should show all rules with weight and up to three decimal places', async () => {
        Shopware.State.commit('swShippingDetail/setShippingMethod', {
            id: '12345',
            prices: [
                {
                    id: 'a1',
                    ruleId: '2',
                    quantityStart: 0,
                    quantityEnd: 2.5,
                    shippingMethodId: 123,
                    calculationRule: 987,
                    calculation: 3,
                    currencyPrice: [
                        {
                            currencyId: 'euro',
                            gross: 50,
                            net: 25,
                            linked: false,
                        },
                    ],
                },
                {
                    id: 'b2',
                    ruleId: '2',
                    quantityStart: 2.6,
                    quantityEnd: 3.52,
                    shippingMethodId: 345,
                    calculationRule: 876,
                    calculation: 3,
                    currencyPrice: [
                        {
                            currencyId: 'euro',
                            gross: 40,
                            net: 20,
                            linked: false,
                        },
                    ],
                },
                {
                    id: 'b3',
                    ruleId: '2',
                    quantityStart: 3.53,
                    quantityEnd: 3.621,
                    shippingMethodId: 345,
                    calculationRule: 876,
                    calculation: 3,
                    currencyPrice: [
                        {
                            currencyId: 'euro',
                            gross: 40,
                            net: 20,
                            linked: false,
                        },
                    ],
                },
                {
                    id: 'b4',
                    ruleId: '2',
                    quantityStart: 3.621,
                    quantityEnd: null,
                    shippingMethodId: 345,
                    calculationRule: 876,
                    calculation: 3,
                    currencyPrice: [
                        {
                            currencyId: 'euro',
                            gross: 40,
                            net: 20,
                            linked: false,
                        },
                    ],
                },
            ],
        });

        const shippingMethod = Shopware.State.get('swShippingDetail').shippingMethod;

        // add remove method to array
        shippingMethod.prices.remove = (id) => {
            shippingMethod.prices = shippingMethod.prices.filter(price => price.id !== id);
        };

        const wrapper = await createWrapper();
        await flushPromises();

        const rowOneQuantityStart = wrapper.find('.sw-data-grid__row--0 .sw-data-grid__cell--quantityStart input');
        const rowOneQuantityEnd = wrapper.find('.sw-data-grid__row--0 .sw-data-grid__cell--quantityEnd input');
        const rowTwoQuantityStart = wrapper.find('.sw-data-grid__row--1 .sw-data-grid__cell--quantityStart input');
        const rowTwoQuantityEnd = wrapper.find('.sw-data-grid__row--1 .sw-data-grid__cell--quantityEnd input');
        const rowThreeQuantityStart = wrapper.find('.sw-data-grid__row--2 .sw-data-grid__cell--quantityStart input');
        const rowThreeQuantityEnd = wrapper.find('.sw-data-grid__row--2 .sw-data-grid__cell--quantityEnd input');
        const rowFourQuantityStart = wrapper.find('.sw-data-grid__row--3 .sw-data-grid__cell--quantityStart input');
        const rowFourQuantityEnd = wrapper.find('.sw-data-grid__row--3 .sw-data-grid__cell--quantityEnd input');

        expect(rowOneQuantityStart.element.value).toBe('0');
        expect(rowOneQuantityEnd.element.value).toBe('2.5');
        expect(rowTwoQuantityStart.element.value).toBe('2.6');
        expect(rowTwoQuantityEnd.element.value).toBe('3.52');
        expect(rowThreeQuantityStart.element.value).toBe('3.53');
        expect(rowThreeQuantityEnd.element.value).toBe('3.621');
        expect(rowFourQuantityStart.element.value).toBe('3.621');
        expect(rowFourQuantityEnd.element.value).toBe('');
    });

    it('all rules should have the right min and max values', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const rowOneQuantityStart = wrapper.find('.sw-data-grid__row--0 .sw-data-grid__cell--quantityStart input');
        const rowOneQuantityEnd = wrapper.find('.sw-data-grid__row--0 .sw-data-grid__cell--quantityEnd input');
        const rowTwoQuantityStart = wrapper.find('.sw-data-grid__row--1 .sw-data-grid__cell--quantityStart input');
        const rowTwoQuantityEnd = wrapper.find('.sw-data-grid__row--1 .sw-data-grid__cell--quantityEnd input');

        expect(rowOneQuantityStart.attributes().min).toBe('0');
        expect(rowOneQuantityStart.attributes().max).toBe('20');

        expect(rowOneQuantityEnd.attributes().min).toBe('1');
        expect(rowOneQuantityEnd.attributes().max).toBeUndefined();

        expect(rowTwoQuantityStart.attributes().min).toBe('20');
        expect(rowTwoQuantityStart.attributes().max).toBeUndefined();

        expect(rowTwoQuantityEnd.attributes().min).toBe('21');
        expect(rowTwoQuantityEnd.attributes().max).toBeUndefined();
    });

    it('should add a new pricing rule and change the values', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const addNewPriceRuleButton = wrapper.find('.sw-settings-shipping-price-matrix__top-container .sw-button__content');
        expect(addNewPriceRuleButton.text()).toBe('sw-settings-shipping.priceMatrix.addNewShippingPrice');

        let lastRowStart = wrapper.find('.sw-data-grid__row:last-child .sw-data-grid__cell--quantityStart input');
        let lastRowEnd = wrapper.find('.sw-data-grid__row:last-child .sw-data-grid__cell--quantityEnd input');
        expect(lastRowStart.element.value).toBe('21');
        expect(lastRowEnd.element.value).toBe('');

        await addNewPriceRuleButton.trigger('click');

        lastRowStart = wrapper.find('.sw-data-grid__row:last-child .sw-data-grid__cell--quantityStart input');
        lastRowEnd = wrapper.find('.sw-data-grid__row:last-child .sw-data-grid__cell--quantityEnd input');
        expect(lastRowStart.element.value).toBe('22');
        expect(lastRowEnd.element.value).toBe('');

        const beforeLastRowStart = wrapper.find(
            '.sw-data-grid__row.sw-data-grid__row--1 .sw-data-grid__cell--quantityStart input',
        );
        const beforeLastRowEnd = wrapper.find(
            '.sw-data-grid__row.sw-data-grid__row--1 .sw-data-grid__cell--quantityEnd input',
        );

        expect(beforeLastRowStart.element.value).toBe('21');
        expect(beforeLastRowEnd.element.value).toBe('21');
    });

    it('should delete the last pricing rule and change the values', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        let rowOneQuantityStart = wrapper.find('.sw-data-grid__row--0 .sw-data-grid__cell--quantityStart input');
        let rowOneQuantityEnd = wrapper.find('.sw-data-grid__row--0 .sw-data-grid__cell--quantityEnd input');
        let rowTwoQuantityStart = wrapper.find('.sw-data-grid__row--1 .sw-data-grid__cell--quantityStart input');
        let rowTwoQuantityEnd = wrapper.find('.sw-data-grid__row--1 .sw-data-grid__cell--quantityEnd input');

        expect(rowOneQuantityStart.element.value).toBe('1');
        expect(rowOneQuantityEnd.element.value).toBe('20');

        expect(rowTwoQuantityStart.element.value).toBe('21');
        expect(rowTwoQuantityEnd.element.value).toBe('');

        const firstRowContextButton = wrapper.find(
            '.sw-data-grid__row.sw-data-grid__row--0 .sw-data-grid__cell--actions .sw-context-button__button',
        );

        await firstRowContextButton.trigger('click');
        await flushPromises();

        const contextMenu = wrapper.find('.sw-context-menu');
        expect(contextMenu.isVisible()).toBeTruthy();

        const deleteButton = contextMenu.find('.sw-context-menu-item--danger');
        expect(deleteButton.isVisible()).toBeTruthy();

        await deleteButton.trigger('click');
        await flushPromises();

        rowOneQuantityStart = wrapper.find('.sw-data-grid__row--0 .sw-data-grid__cell--quantityStart input');
        rowOneQuantityEnd = wrapper.find('.sw-data-grid__row--0 .sw-data-grid__cell--quantityEnd input');
        rowTwoQuantityStart = wrapper.find('.sw-data-grid__row--1');
        rowTwoQuantityEnd = wrapper.find('.sw-data-grid__row--1');

        expect(rowOneQuantityStart.element.value).toBe('1');
        expect(rowOneQuantityEnd.element.value).toBe('');

        expect(rowTwoQuantityStart.exists()).toBe(false);
        expect(rowTwoQuantityEnd.exists()).toBe(false);
    });

    it('should delete a pricing rule and change the values', async () => {
        Shopware.State.commit('swShippingDetail/setShippingMethod', {
            id: '12345',
            prices: [
                {
                    id: 'a1',
                    ruleId: '2',
                    quantityStart: 1,
                    quantityEnd: 20,
                    shippingMethodId: 123,
                    calculationRule: 987,
                    calculation: 1,
                    currencyPrice: [
                        {
                            currencyId: 'euro',
                            gross: 50,
                            net: 25,
                            linked: false,
                        },
                    ],
                },
                {
                    id: 'b2',
                    ruleId: '2',
                    quantityStart: 21,
                    quantityEnd: 25,
                    shippingMethodId: 345,
                    calculationRule: 876,
                    calculation: 1,
                    currencyPrice: [
                        {
                            currencyId: 'euro',
                            gross: 40,
                            net: 20,
                            linked: false,
                        },
                    ],
                },
                {
                    id: 'c3',
                    ruleId: '2',
                    quantityStart: 26,
                    quantityEnd: null,
                    shippingMethodId: 345,
                    calculationRule: 876,
                    calculation: 1,
                    currencyPrice: [
                        {
                            currencyId: 'euro',
                            gross: 40,
                            net: 20,
                            linked: false,
                        },
                    ],
                },
            ],
        });

        const shippingMethod = Shopware.State.get('swShippingDetail').shippingMethod;

        // add remove method to array
        shippingMethod.prices.remove = (id) => {
            shippingMethod.prices = shippingMethod.prices.filter(price => price.id !== id);
        };

        const wrapper = await createWrapper();
        await flushPromises();

        let rowOneQuantityStart = wrapper.find('.sw-data-grid__row--0 .sw-data-grid__cell--quantityStart input');
        let rowOneQuantityEnd = wrapper.find('.sw-data-grid__row--0 .sw-data-grid__cell--quantityEnd input');
        let rowTwoQuantityStart = wrapper.find('.sw-data-grid__row--1 .sw-data-grid__cell--quantityStart input');
        let rowTwoQuantityEnd = wrapper.find('.sw-data-grid__row--1 .sw-data-grid__cell--quantityEnd input');
        let rowThreeQuantityStart = wrapper.find('.sw-data-grid__row--2 .sw-data-grid__cell--quantityStart input');
        let rowThreeQuantityEnd = wrapper.find('.sw-data-grid__row--2 .sw-data-grid__cell--quantityEnd input');

        expect(rowOneQuantityStart.element.value).toBe('1');
        expect(rowOneQuantityEnd.element.value).toBe('20');

        expect(rowTwoQuantityStart.element.value).toBe('21');
        expect(rowTwoQuantityEnd.element.value).toBe('25');

        expect(rowThreeQuantityStart.element.value).toBe('26');
        expect(rowThreeQuantityEnd.element.value).toBe('');

        const firstRowContextButton = wrapper.find(
            '.sw-data-grid__row.sw-data-grid__row--1 .sw-data-grid__cell--actions .sw-context-button__button',
        );

        await firstRowContextButton.trigger('click');
        await flushPromises();

        const contextMenu = wrapper.find('.sw-context-menu');
        expect(contextMenu.isVisible()).toBeTruthy();

        const deleteButton = contextMenu.find('.sw-context-menu-item--danger');
        expect(deleteButton.isVisible()).toBeTruthy();

        await deleteButton.trigger('click');
        await flushPromises();

        rowOneQuantityStart = wrapper.find('.sw-data-grid__row--0 .sw-data-grid__cell--quantityStart input');
        rowOneQuantityEnd = wrapper.find('.sw-data-grid__row--0 .sw-data-grid__cell--quantityEnd input');
        rowTwoQuantityStart = wrapper.find('.sw-data-grid__row--1 .sw-data-grid__cell--quantityStart input');
        rowTwoQuantityEnd = wrapper.find('.sw-data-grid__row--1 .sw-data-grid__cell--quantityEnd input');
        rowThreeQuantityStart = wrapper.find('.sw-data-grid__row--2');
        rowThreeQuantityEnd = wrapper.find('.sw-data-grid__row--2');

        expect(rowOneQuantityStart.element.value).toBe('1');
        expect(rowOneQuantityEnd.element.value).toBe('20');

        expect(rowTwoQuantityStart.element.value).toBe('21');
        expect(rowTwoQuantityEnd.element.value).toBe('');

        expect(rowThreeQuantityStart.exists()).toBe(false);
        expect(rowThreeQuantityEnd.exists()).toBe(false);
    });

    it('should have all fields disabled when property disabled is true', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.setProps({
            disabled: true,
        });

        const addMatrixButton = wrapper.find('.sw-settings-shipping-price-matrices__actions-add-matrix');
        const ruleSelect = wrapper.find('.sw-settings-shipping-price-matrix__top-container-rule-select');
        const addRuleButton = wrapper.find('.sw-settings-shipping-price-matrix__top-container-add-new-rule');
        const toolbarContextButton = wrapper.findComponent('.sw-settings-shipping-price-matrix__price-group-context');

        expect(addMatrixButton.attributes().disabled).toBeDefined();
        expect(addRuleButton.attributes().disabled).toBeDefined();
        expect(toolbarContextButton.props().disabled).toBeDefined();
        expect(ruleSelect.attributes().disabled).toBeDefined();

        const allPricesMatrix = wrapper.findAllComponents('.sw-settings-shipping-price-matrix');
        const numberFields = wrapper.findAll('input[type="number"]');
        const inheritanceSwitches = wrapper.findAllComponents('.sw-inheritance-switch');

        expect(allPricesMatrix.length).toBeGreaterThan(0);
        expect(numberFields.length).toBeGreaterThan(0);
        expect(inheritanceSwitches.length).toBeGreaterThan(0);

        allPricesMatrix.forEach(priceMatrix => {
            expect(priceMatrix.props().disabled).toBe(true);
        });

        numberFields.forEach(numberField => {
            // price field with pound currency should be disabled because of inheritance
            if (numberField.attributes().name.includes('pound')) {
                return;
            }

            expect(numberField.attributes().disabled).toBeDefined();
        });

        inheritanceSwitches.forEach(inheritanceSwitch => {
            expect(inheritanceSwitch.props().disabled).toBe(true);
        });
    });

    it('should have all fields enabled when property disabled is not set', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const addMatrixButton = wrapper.find('.sw-settings-shipping-price-matrices__actions-add-matrix');
        const ruleSelect = wrapper.find('.sw-settings-shipping-price-matrix__top-container-rule-select');
        const addRuleButton = wrapper.find('.sw-settings-shipping-price-matrix__top-container-add-new-rule');
        const toolbarContextButton = wrapper.findComponent('.sw-settings-shipping-price-matrix__price-group-context');

        expect(addMatrixButton.attributes().disabled).toBeUndefined();
        expect(addRuleButton.attributes().disabled).toBeUndefined();
        expect(toolbarContextButton.props().disabled).toBe(false);
        expect(ruleSelect.attributes().disabled).toBeUndefined();

        const allPricesMatrix = wrapper.findAllComponents('.sw-settings-shipping-price-matrix');
        const numberFields = wrapper.findAll('input[type="number"]');
        const inheritanceSwitches = wrapper.findAllComponents('.sw-inheritance-switch');

        expect(allPricesMatrix.length).toBeGreaterThan(0);
        expect(numberFields.length).toBeGreaterThan(0);
        expect(inheritanceSwitches.length).toBeGreaterThan(0);

        allPricesMatrix.forEach(priceMatrix => {
            expect(priceMatrix.props().disabled).toBe(false);
        });

        numberFields.forEach(numberField => {
            // price field with pound currency should be disabled because of inheritance
            if (numberField.attributes().name.includes('pound')) {
                return;
            }
            expect(numberField.attributes().disabled).toBeUndefined();
        });

        inheritanceSwitches.forEach(inheritanceSwitch => {
            expect(inheritanceSwitch.props().disabled).toBe(false);
        });
    });
});
