import { shallowMount } from '@vue/test-utils';

import 'src/module/sw-settings-custom-field/component/sw-custom-field-type-base';
import 'src/module/sw-settings-custom-field/component/sw-custom-field-type-select';

import 'src/app/component/form/sw-checkbox-field';
import 'src/app/component/form/sw-switch-field';


let currentCustomField = {};

function createWrapper() {
    return shallowMount(Shopware.Component.build('sw-custom-field-type-select'), {
        mocks: {
            $i18n: {
                fallbackLocale: 'en-GB'
            }
        },
        propsData: {
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
                    id: '559b6ae735b04e199505fd4c5ac5f22c'
                }],
                products: []
            }
        },
        stubs: {
            'sw-custom-field-translated-labels': true,
            'sw-switch-field': Shopware.Component.build('sw-switch-field'),
            'sw-text-field': {
                props: {
                    value: {
                        type: String,
                        default: ''
                    }
                },
                template: '<input type="text" :value="value" @input="event => $emit(\'input\', event.target.value)" />'
            },
            'sw-base-field': true,
            'sw-field': true,
            'sw-field-error': true,
            'sw-button': true,
            'sw-container': true
        }
    });
}

describe('src/module/sw-settings-custom-field/component/sw-custom-field-type-select', () => {
    beforeEach(() => {
        currentCustomField = {
            name: 'technical_test',
            type: 'select',
            config: {
                label: { 'en-GB': null },
                options: [
                    {
                        label: { 'en-GB': 'translated-label-1' },
                        value: 'label-with-translated-value'
                    },
                    {
                        label: {},
                        value: 'label-without-translated-value'
                    },
                    {
                        label: [],
                        value: 'label-with-incorrect-value'
                    }
                ],
                helpText: { 'en-GB': null },
                placeholder: { 'en-GB': null },
                componentName: 'sw-single-select',
                customFieldType: 'select',
                customFieldPosition: 1
            },
            active: true,
            customFieldSetId: 'd2667dfae415440592a0944fbea2d3ce',
            id: '8e1ab96faf374836a4d68febc8d4f1e1',
            productSearchConfigFields: []
        };
    });

    it('should be a Vue.js component', async () => {
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should allow saving of labels for options', () => {
        const wrapper = createWrapper();

        const labelInputs = wrapper.findAll('.sw-custom-field-type-select__option-label');

        expect(labelInputs.at(0).props('value')).toBe('translated-label-1');
        expect(labelInputs.at(1).props('value')).toBe('');
        expect(labelInputs.at(2).props('value')).toBe('');

        labelInputs.wrappers.forEach((labelInput, index) => labelInput.setValue(`label-${index}`));

        expect(wrapper.vm.currentCustomField.config).toEqual({
            componentName: 'sw-single-select',
            customFieldPosition: 1,
            customFieldType: 'select',
            helpText: {
                'en-GB': null
            },
            label: {
                'en-GB': null
            },
            options: [
                {
                    label: {
                        'en-GB': 'label-0'
                    },
                    value: 'label-with-translated-value'
                },
                {
                    label: {
                        'en-GB': 'label-1'
                    },
                    value: 'label-without-translated-value'
                },
                {
                    label: {
                        'en-GB': 'label-2'
                    },
                    value: 'label-with-incorrect-value'
                }
            ],
            placeholder: {
                'en-GB': null
            }
        });
    });
});
