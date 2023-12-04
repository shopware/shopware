/**
 * @package admin
 */
import { shallowMount } from '@vue/test-utils_v2';

// Mock Component
Shopware.Component.register('sw-mock', {
    template: '<div class="sw-mock"><slot></slot></div>',
});

const config = {
    cartLineItemDimensionWeight: {
        operatorSet: {
            operators: [
                '=',
                '>',
                '>=',
                '<',
                '<=',
                '!=',
                'empty',
            ],
            isMatchAny: false,
        },
        fields: [
            {
                name: 'amount',
                type: 'float',
                config: {
                    unit: 'weight',
                },
            },
        ],
    },
};

describe('app/mixin/generic-condition', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = shallowMount(await Shopware.Component.build('sw-mock'), {
            mixins: [
                Shopware.Mixin.getByName('generic-condition'),
            ],
            mocks: {
                condition: {
                    type: 'cartLineItemDimensionWeight',
                    value: null,
                },
                ensureValueExist: () => {},
            },
        });

        Shopware.State.commit('ruleConditionsConfig/setConfig', config);
    });

    it('should be a Vue.js component', () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should update and convert the field value', () => {
        // config should be set
        expect(wrapper.vm.config).toEqual(config.cartLineItemDimensionWeight);
        expect(wrapper.vm.values).toEqual({});

        // should convert
        wrapper.vm.updateFieldValue('amount', 100, 'g', 'kg');
        expect(wrapper.vm.getVisibleValue('amount')).toBe(100000);
    });

    it('should not convert when "from" unit is undefined', () => {
        wrapper.vm.updateFieldValue('amount', 100, 'g', undefined);
        expect(wrapper.vm.getVisibleValue('amount')).toBe(100);
    });

    it('should not convert when "to" unit is undefined', () => {
        wrapper.vm.updateFieldValue('amount', 100, undefined, 'kg');
        expect(wrapper.vm.getVisibleValue('amount')).toBe(100);
    });

    it('should not convert when "from" and "to" units are equal', () => {
        wrapper.vm.updateFieldValue('amount', 100, 'kg', 'kg');
        expect(wrapper.vm.getVisibleValue('amount')).toBe(100);
    });

    it('should update base unit', () => {
        expect(wrapper.vm.baseUnit).toBeNull();

        // update base unit
        wrapper.vm.setDefaultUnit('kg');
        expect(wrapper.vm.baseUnit).toBe('kg');
    });

    it('should handle unit change', () => {
        expect(wrapper.vm.selectedUnit).toBeNull();

        // change unit
        wrapper.vm.handleUnitChange({
            unit: 'g',
            value: 100000,
        });

        expect(wrapper.vm.selectedUnit).toBe('g');
        expect(wrapper.vm.getVisibleValue('amount')).toBe(100000);
    });

    it('should get the true visible value when visible value was set before', () => {
        wrapper.vm.updateVisibleValue(1);
        expect(wrapper.vm.getVisibleValue('amount')).toBe(1);
    });
});
