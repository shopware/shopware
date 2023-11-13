/**
 * @package system-settings
 */
import { mount } from '@vue/test-utils_v3';

let currentCustomField = {};

async function createWrapper() {
    return mount(await wrapTestComponent('sw-custom-field-type-select', {
        sync: true,
    }), {
        props: {
            currentCustomField,
            set: {
                name: 'technical_test',
                config: { label: { 'en-GB': 'test_label' } },
                active: true,
                global: false,
                position: 1,
                appId: null,
                createdAt: '2021-06-30T08:02:28.996+00:00',
                updatedAt: null,
                apiAlias: null,
                id: 'd2667dfae415440592a0944fbea2d3ce',
                customFields: [],
                relations: [{
                    customFieldSetId: 'd2667dfae415440592a0944fbea2d3ce',
                    entityName: 'product',
                    createdAt: '2021-06-30T08:02:28.996+00:00',
                    updatedAt: null,
                    apiAlias: null,
                    id: '559b6ae735b04e199505fd4c5ac5f22c',
                }],
                products: [],
            },
        },
        global: {
            renderStubDefaultSlot: true,
            mocks: {
                $i18n: {
                    fallbackLocale: 'en-GB',
                },
            },
            stubs: {
                'sw-custom-field-translated-labels': true,
                'sw-switch-field': await wrapTestComponent('sw-switch-field'),
                'sw-text-field': await wrapTestComponent('sw-text-field'),
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-block-field': await wrapTestComponent('sw-block-field'),
                'sw-field': await wrapTestComponent('sw-field'),
                'sw-field-error': true,
                'sw-button': true,
                'sw-container': await wrapTestComponent('sw-container'),
                'sw-contextual-field': await wrapTestComponent('sw-contextual-field'),
            },
        },
    });
}

describe('src/module/sw-settings-custom-field/component/sw-custom-field-type-select', () => {
    beforeEach(async () => {
        currentCustomField = {
            name: 'technical_test',
            type: 'select',
            config: {
                label: { 'en-GB': null },
                options: [
                    {
                        label: { 'en-GB': 'translated-label-1' },
                        value: 'label-with-translated-value',
                    },
                    {
                        label: {},
                        value: 'label-without-translated-value',
                    },
                    {
                        label: [],
                        value: 'label-with-incorrect-value',
                    },
                ],
                helpText: { 'en-GB': null },
                placeholder: { 'en-GB': null },
                componentName: 'sw-single-select',
                customFieldType: 'select',
                customFieldPosition: 1,
            },
            active: true,
            customFieldSetId: 'd2667dfae415440592a0944fbea2d3ce',
            id: '8e1ab96faf374836a4d68febc8d4f1e1',
            productSearchConfigFields: [],
        };
    });

    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should allow saving of labels for options', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const labelInputs = wrapper.findAll('.sw-custom-field-type-select__option-label input');
        expect(labelInputs[0].element.value).toBe('translated-label-1');
        expect(labelInputs[1].element.value).toBe('');
        expect(labelInputs[2].element.value).toBe('');

        // eslint-disable-next-line no-restricted-syntax
        for (const labelInput of labelInputs) {
            const index = labelInputs.indexOf(labelInput);
            // eslint-disable-next-line no-await-in-loop
            await labelInput.setValue(`label-${index}`);
        }

        await flushPromises();

        expect(wrapper.vm.currentCustomField.config).toEqual({
            componentName: 'sw-single-select',
            customFieldPosition: 1,
            customFieldType: 'select',
            helpText: {
                'en-GB': null,
            },
            label: {
                'en-GB': null,
            },
            options: [
                {
                    label: {
                        'en-GB': 'label-0',
                    },
                    value: 'label-with-translated-value',
                },
                {
                    label: {
                        'en-GB': 'label-1',
                    },
                    value: 'label-without-translated-value',
                },
                {
                    label: {
                        'en-GB': 'label-2',
                    },
                    value: 'label-with-incorrect-value',
                },
            ],
            placeholder: {
                'en-GB': null,
            },
        });
    });
});
