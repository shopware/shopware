/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';

function createCustomField(overrides = {}) {
    return {
        name: 'technical_test',
        type: 'int',
        config: {
            label: { 'en-GB': null },
            helpText: { 'en-GB': null },
            placeholder: { 'en-GB': null },
            componentName: 'sw-number-field',
            customFieldType: 'number',
            customFieldPosition: 1,
            numberType: 'int',
            step: null,
            min: null,
            max: null,
            ...overrides,
        },
        active: true,
        customFieldSetId: 'd2667dfae415440592a0944fbea2d3ce',
        id: '8e1ab96faf374836a4d68febc8d4f1e1',
    };
}

const defaultSet = {
    name: 'technical_test',
    config: { label: { 'en-GB': 'test_label' } },
    active: true,
    global: false,
    position: 1,
    id: 'd2667dfae415440592a0944fbea2d3ce',
};

async function createWrapper(customFieldOverrides = {}) {
    return mount(await wrapTestComponent('sw-custom-field-type-number', { sync: true }), {
        props: {
            currentCustomField: createCustomField(customFieldOverrides),
            set: defaultSet,
        },
        global: {
            mocks: {
                $tc: (key) => key,
            },
            stubs: {
                'sw-custom-field-translated-labels': true,
                'sw-container': {
                    template: '<div class="sw-container"><slot /></div>',
                },
                'mt-select': {
                    template: '<div class="mt-select" />',
                    props: [
                        'modelValue',
                        'options',
                    ],
                },
                'mt-number-field': {
                    template: '<div class="mt-number-field" />',
                    props: [
                        'modelValue',
                        'numberType',
                        'step',
                        'digits',
                        'label',
                    ],
                },
            },
        },
    });
}

describe('src/module/sw-settings-custom-field/component/sw-custom-field-type-number', () => {
    it('should provide correct property names for translations', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.propertyNames).toEqual({
            label: 'sw-settings-custom-field.customField.detail.labelLabel',
            placeholder: 'sw-settings-custom-field.customField.detail.labelPlaceholder',
            helpText: 'sw-settings-custom-field.customField.detail.labelHelpText',
        });
    });

    it('should default numberType to int when not set', async () => {
        const wrapper = await createWrapper({ numberType: '' });

        expect(wrapper.vm.currentCustomField.config.numberType).toBe('int');
    });

    it('should provide number type options to the select', async () => {
        const wrapper = await createWrapper();

        const select = wrapper.findComponent('.mt-select');

        expect(select.props('options')).toEqual([
            {
                value: 'int',
                label: 'sw-settings-custom-field.customField.detail.labelInt',
            },
            {
                value: 'float',
                label: 'sw-settings-custom-field.customField.detail.labelFloat',
            },
        ]);
    });

    it('should not override numberType when already set', async () => {
        const wrapper = await createWrapper({ numberType: 'float' });

        expect(wrapper.vm.currentCustomField.config.numberType).toBe('float');
    });

    it.each([
        [
            'int',
            true,
        ],
        [
            'float',
            false,
        ],
    ])('should expose isIntField for numberType %s', async (numberType, expected) => {
        const wrapper = await createWrapper({ numberType });

        expect(wrapper.vm.isIntField).toBe(expected);
    });

    it('should round step, min, and max when switching numberType to int', async () => {
        const wrapper = await createWrapper({
            numberType: 'float',
            step: 1.5,
            min: 2.7,
            max: 10.3,
        });

        wrapper.vm.currentCustomField.config.numberType = 'int';
        await flushPromises();

        expect(wrapper.vm.currentCustomField.config.step).toBe(2);
        expect(wrapper.vm.currentCustomField.config.min).toBe(3);
        expect(wrapper.vm.currentCustomField.config.max).toBe(10);
    });

    it('should clamp rounded step to a minimum of 1 when switching numberType to int', async () => {
        const wrapper = await createWrapper({
            numberType: 'float',
            step: 0.1,
        });

        wrapper.vm.currentCustomField.config.numberType = 'int';
        await flushPromises();

        expect(wrapper.vm.currentCustomField.config.step).toBe(1);
    });

    it('should update type property when switching numberType', async () => {
        const wrapper = await createWrapper({ numberType: 'int' });

        expect(wrapper.vm.currentCustomField.type).toBe('int');

        wrapper.vm.currentCustomField.config.numberType = 'float';
        await flushPromises();

        expect(wrapper.vm.currentCustomField.type).toBe('float');
    });

    it('should update type property when switching numberType to int', async () => {
        const wrapper = await createWrapper({ numberType: 'float' });

        wrapper.vm.currentCustomField.type = 'float';
        expect(wrapper.vm.currentCustomField.type).toBe('float');

        wrapper.vm.currentCustomField.config.numberType = 'int';
        await flushPromises();

        expect(wrapper.vm.currentCustomField.type).toBe('int');
    });

    it('should not round null values when switching numberType to int', async () => {
        const wrapper = await createWrapper({
            numberType: 'float',
            step: null,
            min: null,
            max: null,
        });

        wrapper.vm.currentCustomField.config.numberType = 'int';
        await flushPromises();

        expect(wrapper.vm.currentCustomField.config.step).toBeNull();
        expect(wrapper.vm.currentCustomField.config.min).toBeNull();
        expect(wrapper.vm.currentCustomField.config.max).toBeNull();
    });

    it('should pass number-type float to mt-number-field components when numberType is float', async () => {
        const wrapper = await createWrapper({ numberType: 'float' });
        await flushPromises();

        const numberFields = wrapper.findAllComponents('.mt-number-field');

        numberFields.forEach((field) => {
            expect(field.props('numberType')).toBe('float');
        });
    });

    it('should update mt-number-field props when switching numberType from float to int', async () => {
        const wrapper = await createWrapper({ numberType: 'float' });
        await flushPromises();

        wrapper.vm.currentCustomField.config.numberType = 'int';
        await flushPromises();

        const numberFields = wrapper.findAllComponents('.mt-number-field');

        numberFields.forEach((field) => {
            expect(field.props('numberType')).toBe('int');
        });
    });
});
