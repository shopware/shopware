import { shallowMount } from '@vue/test-utils';
import swCustomEntityInputField from 'src/module/sw-custom-entity/component/sw-custom-entity-input-field';

Shopware.Component.register('sw-custom-entity-input-field', swCustomEntityInputField);

async function createWrapper(propsData = { type: 'string' }) {
    return shallowMount(await Shopware.Component.build('sw-custom-entity-input-field'), {
        propsData,
        provide: {
        },
        stubs: {
            'sw-text-field': {
                template: '<input/>',
            },
            'sw-textarea-field': {
                template: '<input/>',
            },
            'sw-number-field': {
                template: '<input/>',
            },
            'sw-switch-field': {
                template: '<input/>',
            },
            'sw-datepicker': {
                template: '<input/>',
            }
        }
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
    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    ['string', 'text', 'int', 'float', 'boolean', 'date'].forEach((type) => {
        it(`should render basic properties correctly according to type [type="${type}"]`, async () => {
            const mockData = {
                ...basicMockData,
                type,
            };
            const wrapper = await createWrapper();

            await wrapper.setProps(mockData);

            const inputField = wrapper.get(`.sw-custom-entity-input-field__${type}`);
            expect(inputField.attributes().value).toBe(mockData.value);
            expect(inputField.attributes().label).toBe(mockData.label);
            expect(inputField.attributes().placeholder).toBe(mockData.placeholder);
            expect(inputField.attributes()['help-text']).toBe(mockData['help-text']);
        });
    });

    ['int', 'float'].forEach((type) => {
        it(`should render specific properties correctly according to type [type="${type}"]`, async () => {
            const mockData = {
                ...basicMockData,
                type,
            };
            const wrapper = await createWrapper();

            await wrapper.setProps(mockData);

            const inputField = wrapper.get(`.sw-custom-entity-input-field__${type}`);
            expect(inputField.attributes()['number-type']).toBe(type);
        });
    });
});
