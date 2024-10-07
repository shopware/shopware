import { mount } from '@vue/test-utils';

async function createWrapper(props = { type: 'string' }) {
    return mount(await wrapTestComponent('sw-custom-entity-input-field', { sync: true }), {
        global: {
            stubs: {
                'sw-text-field': {
                    template: '<input/>',
                    props: [
                        'value',
                        'label',
                        'placeholder',
                        'helpText',
                    ],
                },
                'sw-textarea-field': {
                    template: '<input/>',
                    props: [
                        'value',
                        'label',
                        'placeholder',
                        'helpText',
                    ],
                },
                'sw-number-field': {
                    template: '<input/>',
                    props: [
                        'value',
                        'label',
                        'placeholder',
                        'helpText',
                        'numberType',
                    ],
                },
                'sw-switch-field': {
                    template: '<input/>',
                    props: [
                        'value',
                        'label',
                        'placeholder',
                        'helpText',
                    ],
                },
                'sw-datepicker': {
                    template: '<input/>',
                    props: [
                        'value',
                        'label',
                        'placeholder',
                        'helpText',
                    ],
                },
            },
        },
        props,
    });
}

const basicMockData = {
    type: 'text',
    value: 'mockValue',
    label: 'mockLabel',
    placeholder: 'mockPlaceholder',
    'help-text': 'mockHelpText',
};

/**
 * @package content
 */
describe('module/sw-custom-entity/component/sw-custom-entity-input-field', () => {
    [
        'string',
        'text',
        'int',
        'float',
        'boolean',
        'date',
    ].forEach((type) => {
        it(`should render basic properties correctly according to type [type="${type}"]`, async () => {
            const mockData = {
                ...basicMockData,
                type,
            };
            const wrapper = await createWrapper();

            await wrapper.setProps(mockData);

            const inputField = wrapper.getComponent(`.sw-custom-entity-input-field__${type}`);
            expect(inputField.props('value')).toBe(mockData.value);
            expect(inputField.props('label')).toBe(mockData.label);
            expect(inputField.props('placeholder')).toBe(mockData.placeholder);
            expect(inputField.props('helpText')).toBe(mockData['help-text']);
        });
    });

    [
        'int',
        'float',
    ].forEach((type) => {
        it(`should render specific properties correctly according to type [type="${type}"]`, async () => {
            const mockData = {
                ...basicMockData,
                type,
            };
            const wrapper = await createWrapper();

            await wrapper.setProps(mockData);

            const inputField = wrapper.getComponent(`.sw-custom-entity-input-field__${type}`);
            expect(inputField.props('numberType')).toBe(type);
        });
    });
});
