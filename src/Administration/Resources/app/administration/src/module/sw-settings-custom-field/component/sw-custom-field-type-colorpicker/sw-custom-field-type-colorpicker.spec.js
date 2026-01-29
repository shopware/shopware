/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';

const currentCustomField = {
    name: 'technical_test',
    type: 'colorpicker',
    config: {
        label: { 'en-GB': null },
        helpText: { 'en-GB': null },
        componentName: 'sw-colorpicker',
        customFieldType: 'colorpicker',
        customFieldPosition: 1,
    },
    active: true,
    customFieldSetId: 'd2667dfae415440592a0944fbea2d3ce',
    id: '8e1ab96faf374836a4d68febc8d4f1e1',
};

const defaultProps = {
    currentCustomField,
    set: {
        name: 'technical_test',
        config: { label: { 'en-GB': 'test_label' } },
        active: true,
        global: false,
        position: 1,
        id: 'd2667dfae415440592a0944fbea2d3ce',
    },
};

async function createWrapper(props = defaultProps) {
    return mount(await wrapTestComponent('sw-custom-field-type-colorpicker', { sync: true }), {
        props,
        global: {
            mocks: {
                $tc: (key) => key,
            },
            stubs: {
                'sw-custom-field-translated-labels': true,
            },
        },
    });
}

describe('src/module/sw-settings-custom-field/component/sw-custom-field-type-colorpicker', () => {
    it('should provide correct property names for translations', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.propertyNames).toEqual({
            label: 'sw-settings-custom-field.customField.detail.labelLabel',
            helpText: 'sw-settings-custom-field.customField.detail.labelHelpText',
        });
    });
});
