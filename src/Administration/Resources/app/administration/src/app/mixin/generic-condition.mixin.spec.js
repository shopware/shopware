/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';

const defaultData = {
    operator: null,
};

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

const conditionDataProviderServiceMock = {
    getOperatorOptionsByIdentifiers: jest.fn(() => []),
};

Shopware.Component.register('sw-mock', {
    template: '<div class="sw-mock"><slot></slot></div>',
    data() {
        return defaultData;
    },
});

async function createWrapper() {
    return mount(await Shopware.Component.build('sw-mock'), {
        global: {
            mixins: [
                Shopware.Mixin.getByName('generic-condition'),
            ],
            mocks: {
                condition: {
                    type: 'cartLineItemDimensionWeight',
                    value: null,
                },
                ensureValueExist: () => {},
                $t: (snippetKey) => snippetKey,
                conditionDataProviderService: conditionDataProviderServiceMock,
            },
        },
    });
}

describe('app/mixin/generic-condition', () => {
    it('should update and convert the field value', async () => {
        Shopware.Store.get('ruleConditionsConfig').config = config;

        const wrapper = await createWrapper();

        // config should be set
        expect(wrapper.vm.config).toEqual(config.cartLineItemDimensionWeight);
        expect(wrapper.vm.values).toEqual({});

        // should convert
        wrapper.vm.updateFieldValue('amount', 100, 'g', 'kg');
        expect(wrapper.vm.getVisibleValue('amount')).toBe(100000);
    });

    it('should not convert when "from" unit is undefined', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.updateFieldValue('amount', 100, 'g', undefined);
        expect(wrapper.vm.getVisibleValue('amount')).toBe(100);
    });

    it('should not convert when "to" unit is undefined', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.updateFieldValue('amount', 100, undefined, 'kg');
        expect(wrapper.vm.getVisibleValue('amount')).toBe(100);
    });

    it('should not convert when "from" and "to" units are equal', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.updateFieldValue('amount', 100, 'kg', 'kg');
        expect(wrapper.vm.getVisibleValue('amount')).toBe(100);
    });

    it('should update base unit', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.baseUnit).toBeNull();

        // update base unit
        wrapper.vm.setDefaultUnit('kg');
        expect(wrapper.vm.baseUnit).toBe('kg');
    });

    it('should handle unit change', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.selectedUnit).toBeNull();

        // change unit
        wrapper.vm.handleUnitChange({
            unit: 'g',
            value: 100000,
        });

        expect(wrapper.vm.selectedUnit).toBe('g');
        expect(wrapper.vm.getVisibleValue('amount')).toBe(100000);
    });

    it('should get the true visible value when visible value was set before', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.updateVisibleValue(1);
        expect(wrapper.vm.getVisibleValue('amount')).toBe(1);
    });

    it('should translate options for single-select fields in getBind', async () => {
        const wrapper = await createWrapper();

        const field = {
            name: 'status',
            type: 'single-select',
            config: {
                options: [
                    'option1',
                    'option2',
                ],
            },
        };

        const result = wrapper.vm.getBind(field);

        expect(result.config.options).toEqual([
            {
                label: 'global.sw-condition-generic.cartLineItemDimensionWeight.status.options.option1',
                value: 'option1',
            },
            {
                label: 'global.sw-condition-generic.cartLineItemDimensionWeight.status.options.option2',
                value: 'option2',
            },
        ]);
    });

    it('should translate options for multi-select fields in getBind', async () => {
        const wrapper = await createWrapper();

        const field = {
            name: 'tags',
            type: 'multi-select',
            config: {
                options: [
                    'optionA',
                    'optionB',
                ],
            },
        };

        const result = wrapper.vm.getBind(field);

        expect(result.config.options).toEqual([
            {
                label: 'global.sw-condition-generic.cartLineItemDimensionWeight.tags.options.optionA',
                value: 'optionA',
            },
            {
                label: 'global.sw-condition-generic.cartLineItemDimensionWeight.tags.options.optionB',
                value: 'optionB',
            },
        ]);
    });

    it.each([
        { name: 'date + between', type: 'date', operator: 'between', expected: true },
        { name: 'datetime + between', type: 'datetime', operator: 'between', expected: true },
        { name: 'date + equals', type: 'date', operator: '=', expected: false },
        { name: 'datetime + equals', type: 'datetime', operator: '=', expected: false },
        { name: 'string + between', type: 'string', operator: 'between', expected: false },
    ])('should validate if field has between operator: $name', async ({ type, operator, expected }) => {
        const wrapper = await createWrapper();

        await wrapper.setData({ operator });

        expect(wrapper.vm.isBetweenDateField({ type })).toBe(expected);
    });

    it('should write the between value to the field', async () => {
        const wrapper = await createWrapper();

        const value = { from: '2026-01-01', to: '2026-12-31' };

        wrapper.vm.updateBetweenDateValue('amount', value);

        expect(wrapper.vm.condition.value).toEqual({ amount: value });
    });

    it.each([
        { name: 'multi-entity-id-select', fieldType: 'multi-entity-id-select', expected: true },
        { name: 'multi-select', fieldType: 'multi-select', expected: true },
        { name: 'tagged', fieldType: 'tagged', expected: true },
        { name: 'single-entity-id-select', fieldType: 'single-entity-id-select', expected: false },
        { name: 'int', fieldType: 'int', expected: false },
        { name: 'float', fieldType: 'float', expected: false },
    ])('derives operator labels from field cardinality, not isMatchAny: $name', async ({ fieldType, expected }) => {
        Shopware.Store.get('ruleConditionsConfig').config = {
            cartLineItemDimensionWeight: {
                operatorSet: {
                    operators: [
                        '=',
                        '!=',
                    ],
                    isMatchAny: !expected,
                },
                fields: [{ name: 'field', type: fieldType, config: {} }],
            },
        };

        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.operators).toEqual([]);
        expect(conditionDataProviderServiceMock.getOperatorOptionsByIdentifiers).toHaveBeenCalledWith(
            [
                '=',
                '!=',
            ],
            expected,
        );
    });
});
