import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-settings-shipping/component/sw-settings-shipping-price-matrices';
import 'src/module/sw-settings-shipping/component/sw-settings-shipping-price-matrix';
import 'src/app/component/base/sw-button';
import 'src/app/component/context-menu/sw-context-button';
import 'src/app/component/data-grid/sw-data-grid';
import 'src/app/component/form/sw-text-field';
import 'src/app/component/form/sw-number-field';
import 'src/app/component/context-menu/sw-context-menu';
import 'src/app/component/context-menu/sw-context-menu-item';
import 'src/app/component/utils/sw-inherit-wrapper';
import 'src/app/component/base/sw-inheritance-switch';
import state from 'src/module/sw-settings-shipping/page/sw-settings-shipping-detail/state';

Shopware.State.registerModule('swShippingDetail', state);

const swSettingsShippingPriceMatrix = Shopware.Component.build('sw-settings-shipping-price-matrix');

const createWrapper = () => {
    return shallowMount(Shopware.Component.build('sw-settings-shipping-price-matrices'), {
        store: Shopware.State._store,
        stubs: {
            'sw-settings-shipping-price-matrix': swSettingsShippingPriceMatrix,
            'sw-card': true,
            'sw-alert': true,
            'sw-container': true,
            'sw-select-rule-create': true,
            'sw-single-select': true,
            'sw-icon': true,
            'sw-popover': true,
            'sw-text-field': Shopware.Component.build('sw-text-field'),
            'sw-button': Shopware.Component.build('sw-button'),
            'sw-context-button': Shopware.Component.build('sw-context-button'),
            'sw-data-grid': Shopware.Component.build('sw-data-grid'),
            'sw-number-field': {
                template: '<input type="number" v-model="value" />',
                props: {
                    value: 0
                }
            },
            'sw-context-menu': Shopware.Component.build('sw-context-menu'),
            'sw-context-menu-item': Shopware.Component.build('sw-context-menu-item'),
            'sw-checkbox-field': true,
            'sw-data-grid-settings': true,
            'sw-inherit-wrapper': Shopware.Component.build('sw-inherit-wrapper'),
            'sw-inheritance-switch': Shopware.Component.build('sw-inheritance-switch')
        },
        mocks: {
            $te: () => false
        },
        provide: {
            repositoryFactory: {
                create: (name) => {
                    if (name === 'rule') {
                        return {
                            search: () => Promise.resolve([]),
                            get: () => Promise.resolve({})
                        };
                    }

                    if (name === 'shipping_method') {
                        return {};
                    }

                    if (name === 'shipping_method_price') {
                        return {
                            create: () => Promise.resolve([])
                        };
                    }

                    return null;
                }
            }
        }
    });
};

describe('module/sw-settings-shipping/component/sw-settings-shipping-price-matrices', () => {
    beforeEach(() => {
        Shopware.State.commit('swShippingDetail/setCurrencies', [
            { id: 'euro', translated: { name: 'Euro' }, isSystemDefault: true },
            { id: 'dollar', translated: { name: 'Dollar' } },
            { id: 'pound', translated: { name: 'Pound' } }
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
                            linked: false
                        }
                    ]
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
                            linked: false
                        }
                    ]
                }
            ]
        });

        const shippingMethod = Shopware.State.get('swShippingDetail').shippingMethod;

        // add remove method to array
        shippingMethod.prices.remove = (id) => {
            shippingMethod.prices = shippingMethod.prices.filter(price => price.id !== id);
        };
    });

    it('should be a Vue.js component', async () => {
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should render one shipping price matrix', async () => {
        const wrapper = createWrapper();

        Shopware.State.commit('swShippingDetail/setShippingMethod', {
            prices: [
                { ruleId: '1' },
                { ruleId: '1' }
            ]
        });

        const matrices = wrapper.findAllComponents(swSettingsShippingPriceMatrix);

        expect(matrices).toHaveLength(1);
    });

    it('should render two shipping price matrices', async () => {
        const wrapper = createWrapper();

        await Shopware.State.commit('swShippingDetail/setShippingMethod', {
            prices: [
                { ruleId: '1' },
                { ruleId: '2' }
            ]
        });

        const matrices = wrapper.findAllComponents(swSettingsShippingPriceMatrix);

        expect(matrices).toHaveLength(2);
    });

    it('should render five shipping price matrices', async () => {
        const wrapper = createWrapper();


        await Shopware.State.commit('swShippingDetail/setShippingMethod', {
            prices: [
                { ruleId: '1' },
                { ruleId: '2' },
                { ruleId: '3' },
                { ruleId: '4' },
                { ruleId: '5' }
            ]
        });

        const matrices = wrapper.findAllComponents(swSettingsShippingPriceMatrix);

        expect(matrices).toHaveLength(5);
    });

    it('should enable the button when there are available rules', async () => {
        const wrapper = createWrapper();

        Shopware.State.commit('swShippingDetail/setShippingMethod', {
            prices: [
                { ruleId: '1' },
                { ruleId: '2' }
            ]
        });

        const addPriceMatrixButton = wrapper.find('.sw-settings-shipping-price-matrices__actions .sw-button');
        expect(addPriceMatrixButton.attributes('disabled')).toBeFalsy();
    });

    it('should duplicate the price matrix', async () => {
        const wrapper = createWrapper();

        Shopware.State.commit('swShippingDetail/setShippingMethod', {
            id: 7,
            prices: [
                {
                    ruleId: '1',
                    quantityStart: 15,
                    quantityEnd: 30,
                    shippingMethodId: 123,
                    calculationRule: 987,
                    currencyPrice: 444
                },
                {
                    ruleId: '1',
                    quantityStart: 25,
                    quantityEnd: 35,
                    shippingMethodId: 345,
                    calculationRule: 876,
                    currencyPrice: 555
                },
                {
                    ruleId: '1',
                    quantityStart: 45,
                    quantityEnd: 60,
                    shippingMethodId: 678,
                    calculationRule: 765,
                    currencyPrice: 666
                },
                { ruleId: '2' }
            ]
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
            expect(duplication.ruleId).toEqual(null);
        });
    });

    it('should delete the shipping price group', async () => {
        const wrapper = createWrapper();

        Shopware.State.commit('swShippingDetail/setShippingMethod', {
            id: 7,
            prices: [
                { ruleId: '1' },
                { ruleId: '2' },
                { ruleId: '2' },
                { ruleId: '3' }
            ]
        });

        expect(Object.keys(wrapper.vm.shippingPriceGroups)).toContain('2');

        wrapper.vm.onDeletePriceMatrix(wrapper.vm.shippingPriceGroups['2']);

        expect(Object.keys(wrapper.vm.shippingPriceGroups)).not.toContain('2');
    });

    it('should add the shipping price group', async () => {
        const wrapper = createWrapper();

        Shopware.State.commit('swShippingDetail/setShippingMethod', {
            id: 7,
            prices: [
                { ruleId: '1' },
                { ruleId: '2' },
                { ruleId: '2' },
                { ruleId: '3' }
            ]
        });

        Shopware.State.get('swShippingDetail').shippingMethod.prices.add = (value) => {
            Shopware.State.get('swShippingDetail').shippingMethod.prices.push(value);
        };

        expect(Object.keys(wrapper.vm.shippingPriceGroups)).not.toContain('null');

        wrapper.vm.onAddNewPriceGroup();

        expect(Object.keys(wrapper.vm.shippingPriceGroups)).toContain('null');
    });

    it('should show all rules with matching prices', async () => {
        const wrapper = createWrapper();

        const rowOneQuantityStart = wrapper.find('.sw-data-grid__row--0 .sw-data-grid__cell--quantityStart input');
        const rowOneQuantityEnd = wrapper.find('.sw-data-grid__row--0 .sw-data-grid__cell--quantityEnd input');
        const rowTwoQuantityStart = wrapper.find('.sw-data-grid__row--1 .sw-data-grid__cell--quantityStart input');
        const rowTwoQuantityEnd = wrapper.find('.sw-data-grid__row--1 .sw-data-grid__cell--quantityEnd input');

        expect(rowOneQuantityStart.element.value).toEqual('1');
        expect(rowOneQuantityEnd.element.value).toEqual('20');
        expect(rowTwoQuantityStart.element.value).toEqual('21');
        expect(rowTwoQuantityEnd.element.value).toEqual('');
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
                            linked: false
                        }
                    ]
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
                            linked: false
                        }
                    ]
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
                            linked: false
                        }
                    ]
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
                            linked: false
                        }
                    ]
                }
            ]
        });

        const shippingMethod = Shopware.State.get('swShippingDetail').shippingMethod;

        // add remove method to array
        shippingMethod.prices.remove = (id) => {
            shippingMethod.prices = shippingMethod.prices.filter(price => price.id !== id);
        };

        const wrapper = createWrapper();

        const rowOneQuantityStart = wrapper.find('.sw-data-grid__row--0 .sw-data-grid__cell--quantityStart input');
        const rowOneQuantityEnd = wrapper.find('.sw-data-grid__row--0 .sw-data-grid__cell--quantityEnd input');
        const rowTwoQuantityStart = wrapper.find('.sw-data-grid__row--1 .sw-data-grid__cell--quantityStart input');
        const rowTwoQuantityEnd = wrapper.find('.sw-data-grid__row--1 .sw-data-grid__cell--quantityEnd input');
        const rowThreeQuantityStart = wrapper.find('.sw-data-grid__row--2 .sw-data-grid__cell--quantityStart input');
        const rowThreeQuantityEnd = wrapper.find('.sw-data-grid__row--2 .sw-data-grid__cell--quantityEnd input');
        const rowFourQuantityStart = wrapper.find('.sw-data-grid__row--3 .sw-data-grid__cell--quantityStart input');
        const rowFourQuantityEnd = wrapper.find('.sw-data-grid__row--3 .sw-data-grid__cell--quantityEnd input');

        expect(rowOneQuantityStart.element.value).toEqual('0');
        expect(rowOneQuantityEnd.element.value).toEqual('2.5');
        expect(rowTwoQuantityStart.element.value).toEqual('2.6');
        expect(rowTwoQuantityEnd.element.value).toEqual('3.52');
        expect(rowThreeQuantityStart.element.value).toEqual('3.53');
        expect(rowThreeQuantityEnd.element.value).toEqual('3.621');
        expect(rowFourQuantityStart.element.value).toEqual('3.621');
        expect(rowFourQuantityEnd.element.value).toEqual('');
    });

    it('all rules should have the right min and max values', async () => {
        const wrapper = createWrapper();

        const rowOneQuantityStart = wrapper.find('.sw-data-grid__row--0 .sw-data-grid__cell--quantityStart input');
        const rowOneQuantityEnd = wrapper.find('.sw-data-grid__row--0 .sw-data-grid__cell--quantityEnd input');
        const rowTwoQuantityStart = wrapper.find('.sw-data-grid__row--1 .sw-data-grid__cell--quantityStart input');
        const rowTwoQuantityEnd = wrapper.find('.sw-data-grid__row--1 .sw-data-grid__cell--quantityEnd input');

        expect(rowOneQuantityStart.attributes().min).toEqual('0');
        expect(rowOneQuantityStart.attributes().max).toEqual('20');

        expect(rowOneQuantityEnd.attributes().min).toEqual('1');
        expect(rowOneQuantityEnd.attributes().max).toEqual(undefined);

        expect(rowTwoQuantityStart.attributes().min).toEqual('20');
        expect(rowTwoQuantityStart.attributes().max).toEqual(undefined);

        expect(rowTwoQuantityEnd.attributes().min).toEqual('21');
        expect(rowTwoQuantityEnd.attributes().max).toEqual(undefined);
    });

    it('should add a new pricing rule and change the values', async () => {
        const wrapper = createWrapper();

        const addNewPriceRuleButton = wrapper.find('.sw-settings-shipping-price-matrix__top-container .sw-button__content');
        expect(addNewPriceRuleButton.text()).toEqual('sw-settings-shipping.priceMatrix.addNewShippingPrice');

        let lastRowStart = wrapper.find('.sw-data-grid__row:last-child .sw-data-grid__cell--quantityStart input');
        let lastRowEnd = wrapper.find('.sw-data-grid__row:last-child .sw-data-grid__cell--quantityEnd input');
        expect(lastRowStart.element.value).toEqual('21');
        expect(lastRowEnd.element.value).toEqual('');

        await addNewPriceRuleButton.trigger('click');

        lastRowStart = wrapper.find('.sw-data-grid__row:last-child .sw-data-grid__cell--quantityStart input');
        lastRowEnd = wrapper.find('.sw-data-grid__row:last-child .sw-data-grid__cell--quantityEnd input');
        expect(lastRowStart.element.value).toEqual('22');
        expect(lastRowEnd.element.value).toEqual('');

        const beforeLastRowStart = wrapper.find(
            '.sw-data-grid__row.sw-data-grid__row--1 .sw-data-grid__cell--quantityStart input'
        );
        const beforeLastRowEnd = wrapper.find(
            '.sw-data-grid__row.sw-data-grid__row--1 .sw-data-grid__cell--quantityEnd input'
        );

        expect(beforeLastRowStart.element.value).toEqual('21');
        expect(beforeLastRowEnd.element.value).toEqual('21');
    });

    it('should delete the last pricing rule and change the values', async () => {
        const wrapper = createWrapper();

        let rowOneQuantityStart = wrapper.find('.sw-data-grid__row--0 .sw-data-grid__cell--quantityStart input');
        let rowOneQuantityEnd = wrapper.find('.sw-data-grid__row--0 .sw-data-grid__cell--quantityEnd input');
        let rowTwoQuantityStart = wrapper.find('.sw-data-grid__row--1 .sw-data-grid__cell--quantityStart input');
        let rowTwoQuantityEnd = wrapper.find('.sw-data-grid__row--1 .sw-data-grid__cell--quantityEnd input');

        expect(rowOneQuantityStart.element.value).toEqual('1');
        expect(rowOneQuantityEnd.element.value).toEqual('20');

        expect(rowTwoQuantityStart.element.value).toEqual('21');
        expect(rowTwoQuantityEnd.element.value).toEqual('');

        const firstRowContextButton = wrapper.find(
            '.sw-data-grid__row.sw-data-grid__row--0 .sw-data-grid__cell--actions .sw-context-button__button'
        );

        await firstRowContextButton.trigger('click');

        const contextMenu = wrapper.find('.sw-context-menu');
        expect(contextMenu.isVisible()).toBeTruthy();

        const deleteButton = contextMenu.find('.sw-context-menu-item--danger');
        expect(deleteButton.isVisible()).toBeTruthy();

        await deleteButton.trigger('click');

        rowOneQuantityStart = wrapper.find('.sw-data-grid__row--0 .sw-data-grid__cell--quantityStart input');
        rowOneQuantityEnd = wrapper.find('.sw-data-grid__row--0 .sw-data-grid__cell--quantityEnd input');
        rowTwoQuantityStart = wrapper.find('.sw-data-grid__row--1');
        rowTwoQuantityEnd = wrapper.find('.sw-data-grid__row--1');

        expect(rowOneQuantityStart.element.value).toEqual('1');
        expect(rowOneQuantityEnd.element.value).toEqual('');

        expect(rowTwoQuantityStart.exists()).toEqual(false);
        expect(rowTwoQuantityEnd.exists()).toEqual(false);
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
                            linked: false
                        }
                    ]
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
                            linked: false
                        }
                    ]
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
                            linked: false
                        }
                    ]
                }
            ]
        });

        const shippingMethod = Shopware.State.get('swShippingDetail').shippingMethod;

        // add remove method to array
        shippingMethod.prices.remove = (id) => {
            shippingMethod.prices = shippingMethod.prices.filter(price => price.id !== id);
        };

        const wrapper = createWrapper();

        let rowOneQuantityStart = wrapper.find('.sw-data-grid__row--0 .sw-data-grid__cell--quantityStart input');
        let rowOneQuantityEnd = wrapper.find('.sw-data-grid__row--0 .sw-data-grid__cell--quantityEnd input');
        let rowTwoQuantityStart = wrapper.find('.sw-data-grid__row--1 .sw-data-grid__cell--quantityStart input');
        let rowTwoQuantityEnd = wrapper.find('.sw-data-grid__row--1 .sw-data-grid__cell--quantityEnd input');
        let rowThreeQuantityStart = wrapper.find('.sw-data-grid__row--2 .sw-data-grid__cell--quantityStart input');
        let rowThreeQuantityEnd = wrapper.find('.sw-data-grid__row--2 .sw-data-grid__cell--quantityEnd input');

        expect(rowOneQuantityStart.element.value).toEqual('1');
        expect(rowOneQuantityEnd.element.value).toEqual('20');

        expect(rowTwoQuantityStart.element.value).toEqual('21');
        expect(rowTwoQuantityEnd.element.value).toEqual('25');

        expect(rowThreeQuantityStart.element.value).toEqual('26');
        expect(rowThreeQuantityEnd.element.value).toEqual('');

        const firstRowContextButton = wrapper.find(
            '.sw-data-grid__row.sw-data-grid__row--1 .sw-data-grid__cell--actions .sw-context-button__button'
        );

        await firstRowContextButton.trigger('click');

        const contextMenu = wrapper.find('.sw-context-menu');
        expect(contextMenu.isVisible()).toBeTruthy();

        const deleteButton = contextMenu.find('.sw-context-menu-item--danger');
        expect(deleteButton.isVisible()).toBeTruthy();

        await deleteButton.trigger('click');

        rowOneQuantityStart = wrapper.find('.sw-data-grid__row--0 .sw-data-grid__cell--quantityStart input');
        rowOneQuantityEnd = wrapper.find('.sw-data-grid__row--0 .sw-data-grid__cell--quantityEnd input');
        rowTwoQuantityStart = wrapper.find('.sw-data-grid__row--1 .sw-data-grid__cell--quantityStart input');
        rowTwoQuantityEnd = wrapper.find('.sw-data-grid__row--1 .sw-data-grid__cell--quantityEnd input');
        rowThreeQuantityStart = wrapper.find('.sw-data-grid__row--2');
        rowThreeQuantityEnd = wrapper.find('.sw-data-grid__row--2');

        expect(rowOneQuantityStart.element.value).toEqual('1');
        expect(rowOneQuantityEnd.element.value).toEqual('20');

        expect(rowTwoQuantityStart.element.value).toEqual('21');
        expect(rowTwoQuantityEnd.element.value).toEqual('');

        expect(rowThreeQuantityStart.exists()).toEqual(false);
        expect(rowThreeQuantityEnd.exists()).toEqual(false);
    });

    it('should have all fields disabled when property disabled is true', async () => {
        const wrapper = createWrapper();

        await wrapper.setProps({
            disabled: true
        });

        const addMatrixButton = wrapper.find('.sw-settings-shipping-price-matrices__actions-add-matrix');
        const ruleSelect = wrapper.find('.sw-settings-shipping-price-matrix__top-container-rule-select');
        const addRuleButton = wrapper.find('.sw-settings-shipping-price-matrix__top-container-add-new-rule');
        const toolbarContextButton = wrapper.find('.sw-settings-shipping-price-matrix__price-group-context');

        expect(addMatrixButton.attributes().disabled).toBe('disabled');
        expect(addRuleButton.attributes().disabled).toBe('disabled');
        expect(toolbarContextButton.props().disabled).toBe(true);
        expect(ruleSelect.attributes().disabled).toBe('true');

        const allPricesMatrix = wrapper.findAllComponents(swSettingsShippingPriceMatrix);
        const numberFields = wrapper.findAll('input[type="number"]');
        const inheritanceSwitches = wrapper.findAll('.sw-inheritance-switch');

        expect(allPricesMatrix.length).toBeGreaterThan(0);
        expect(numberFields.length).toBeGreaterThan(0);
        expect(inheritanceSwitches.length).toBeGreaterThan(0);

        allPricesMatrix.wrappers.forEach(priceMatrix => {
            expect(priceMatrix.props().disabled).toBe(true);
        });

        numberFields.wrappers.forEach(numberField => {
            // price field with pound currency should be disabled because of inheritance
            if (numberField.attributes().name.includes('pound')) {
                return;
            }

            expect(numberField.attributes().disabled).toBe('disabled');
        });

        inheritanceSwitches.wrappers.forEach(inheritanceSwitch => {
            expect(inheritanceSwitch.props().disabled).toBe(true);
        });
    });

    it('should have all fields enabled when property disabled is not set', async () => {
        const wrapper = createWrapper();

        const addMatrixButton = wrapper.find('.sw-settings-shipping-price-matrices__actions-add-matrix');
        const ruleSelect = wrapper.find('.sw-settings-shipping-price-matrix__top-container-rule-select');
        const addRuleButton = wrapper.find('.sw-settings-shipping-price-matrix__top-container-add-new-rule');
        const toolbarContextButton = wrapper.find('.sw-settings-shipping-price-matrix__price-group-context');

        expect(addMatrixButton.attributes().disabled).toBeUndefined();
        expect(addRuleButton.attributes().disabled).toBeUndefined();
        expect(toolbarContextButton.props().disabled).toBe(false);
        expect(ruleSelect.attributes().disabled).toBeUndefined();

        const allPricesMatrix = wrapper.findAllComponents(swSettingsShippingPriceMatrix);
        const numberFields = wrapper.findAll('input[type="number"]');
        const inheritanceSwitches = wrapper.findAll('.sw-inheritance-switch');

        expect(allPricesMatrix.length).toBeGreaterThan(0);
        expect(numberFields.length).toBeGreaterThan(0);
        expect(inheritanceSwitches.length).toBeGreaterThan(0);

        allPricesMatrix.wrappers.forEach(priceMatrix => {
            expect(priceMatrix.props().disabled).toBe(false);
        });

        numberFields.wrappers.forEach(numberField => {
            // price field with pound currency should be disabled because of inheritance
            if (numberField.attributes().name.includes('pound')) {
                return;
            }
            expect(numberField.attributes().disabled).toBeUndefined();
        });

        inheritanceSwitches.wrappers.forEach(inheritanceSwitch => {
            expect(inheritanceSwitch.props().disabled).toBe(false);
        });
    });
});
