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
                'mt-textarea': {
                    template: '<input/>',
                    props: [
                        'modelValue',
                        'label',
                        'placeholder',
                        'helpText',
                    ],
                },
                'mt-number-field': {
                    template: '<input/>',
                    props: [
                        'modelValue',
                        'label',
                        'placeholder',
                        'helpText',
                        'numberType',
                    ],
                },
                'mt-datepicker': {
                    template: '<input/>',
                    props: [
                        'modelValue',
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
 * @sw-package framework
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
            const modelValueTypes = [
                'text',
                'string',
                'date',
            ];
            let propType = modelValueTypes.includes(type) ? 'modelValue' : 'value';

            if (type === 'boolean') {
                propType = 'checked';
                mockData.value = true;
                mockData.placeholder = undefined;
            }

            if (type === 'int' || type === 'float') {
                propType = 'modelValue';
            }

            expect(inputField.props(propType)).toBe(mockData.value);
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
