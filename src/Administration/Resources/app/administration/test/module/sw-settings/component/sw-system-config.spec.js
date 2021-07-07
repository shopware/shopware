/* eslint-disable max-len */
import { shallowMount, createLocalVue } from '@vue/test-utils';
import { kebabCase } from 'lodash';
import uuid from 'src/../test/_helper_/uuid';
import flushPromises from 'flush-promises';
import 'src/app/component/form/sw-custom-field-set-renderer';
import 'src/app/component/utils/sw-inherit-wrapper';
import 'src/app/component/form/sw-form-field-renderer';
import 'src/app/component/form/sw-field';
import 'src/app/component/base/sw-button';
import 'src/app/component/base/sw-label';
import 'src/app/component/form/sw-text-field';
import 'src/app/component/form/sw-switch-field';
import 'src/app/component/form/sw-number-field';
import 'src/app/component/form/sw-checkbox-field';
import 'src/app/component/form/field-base/sw-contextual-field';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/field-base/sw-field-error';
import 'src/app/component/base/sw-inheritance-switch';
import 'src/app/component/base/sw-icon';
import 'src/app/component/form/select/base/sw-single-select';
import 'src/app/component/form/select/base/sw-multi-select';
import 'src/app/component/form/select/entity/sw-entity-multi-select';
import 'src/app/component/form/select/entity/sw-entity-multi-id-select';
import 'src/app/component/form/select/base/sw-select-base';
import 'src/app/component/form/select/base/sw-select-result-list';
import 'src/app/component/form/select/base/sw-select-result';
import 'src/app/component/form/select/base/sw-select-selection-list';
import 'src/app/component/utils/sw-popover';
import 'src/app/component/base/sw-highlight-text';
import 'src/app/component/media/sw-media-field';
import 'src/app/component/media/sw-media-media-item';
import 'src/app/component/media/sw-media-base-item';
import 'src/app/component/media/sw-media-preview-v2';
import 'src/app/filter/media-name.filter';
import 'src/module/sw-settings/component/sw-system-config';
import 'src/app/component/base/sw-card';
import 'src/app/component/structure/sw-sales-channel-switch';
import 'src/app/component/form/select/entity/sw-entity-single-select';
import 'src/app/component/form/sw-textarea-field';
import 'src/app/component/form/sw-url-field';
import 'src/app/component/form/sw-password-field';
import 'src/app/filter/unicode-uri';

/** @type Wrapper */
let wrapper;

function createWrapper(defaultValues = {}) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});
    localVue.directive('popover', {});
    localVue.filter('mediaName', Shopware.Filter.getByName('mediaName'));
    localVue.filter('unicodeUri', Shopware.Filter.getByName('unicodeUri'));

    return shallowMount(Shopware.Component.build('sw-system-config'), {
        localVue,
        propsData: {
            salesChannelSwitchable: true,
            domain: 'ConfigRenderer.config'
        },
        stubs: {
            'sw-form-field-renderer': Shopware.Component.build('sw-form-field-renderer'),
            'sw-card': Shopware.Component.build('sw-card'),
            'sw-sales-channel-switch': Shopware.Component.build('sw-sales-channel-switch'),
            'sw-entity-single-select': Shopware.Component.build('sw-entity-single-select'),
            'sw-button': Shopware.Component.build('sw-button'),
            'sw-label': Shopware.Component.build('sw-label'),
            'sw-inherit-wrapper': Shopware.Component.build('sw-inherit-wrapper'),
            'sw-inheritance-switch': Shopware.Component.build('sw-inheritance-switch'),
            'sw-field': Shopware.Component.build('sw-field'),
            'sw-text-field': Shopware.Component.build('sw-text-field'),
            'sw-password-field': Shopware.Component.build('sw-password-field'),
            'sw-textarea-field': Shopware.Component.build('sw-textarea-field'),
            'sw-contextual-field': Shopware.Component.build('sw-contextual-field'),
            'sw-switch-field': Shopware.Component.build('sw-switch-field'),
            'sw-number-field': Shopware.Component.build('sw-number-field'),
            'sw-checkbox-field': Shopware.Component.build('sw-checkbox-field'),
            'sw-block-field': Shopware.Component.build('sw-block-field'),
            'sw-base-field': Shopware.Component.build('sw-base-field'),
            'sw-field-error': Shopware.Component.build('sw-field-error'),
            'sw-icon': {
                template: '<div class="sw-icon" @click="$emit(\'click\')"></div>'
            },
            'sw-single-select': Shopware.Component.build('sw-single-select'),
            'sw-multi-select': Shopware.Component.build('sw-multi-select'),
            'sw-entity-multi-select': Shopware.Component.build('sw-entity-multi-select'),
            'sw-entity-multi-id-select': Shopware.Component.build('sw-entity-multi-id-select'),
            'sw-select-base': Shopware.Component.build('sw-select-base'),
            'sw-select-result-list': Shopware.Component.build('sw-select-result-list'),
            'sw-select-result': Shopware.Component.build('sw-select-result'),
            'sw-select-selection-list': Shopware.Component.build('sw-select-selection-list'),
            'sw-popover': Shopware.Component.build('sw-popover'),
            'sw-highlight-text': Shopware.Component.build('sw-highlight-text'),
            'sw-media-field': Shopware.Component.build('sw-media-field'),
            'sw-url-field': Shopware.Component.build('sw-url-field'),
            'sw-media-media-item': Shopware.Component.build('sw-media-media-item'),
            'sw-media-base-item': Shopware.Component.build('sw-media-base-item'),
            'sw-media-preview-v2': Shopware.Component.build('sw-media-preview-v2'),
            'sw-colorpicker': Shopware.Component.build('sw-text-field'),
            'sw-upload-listener': true,
            'sw-simple-search-field': true,
            'sw-loader': true,
            'sw-datepicker': Shopware.Component.build('sw-text-field'),
            'sw-text-editor': Shopware.Component.build('sw-text-field')
        },
        provide: {
            systemConfigApiService: {
                getConfig: () => Promise.resolve(createConfig()),
                getValues: (domain, salesChannelId) => {
                    if (defaultValues[domain] && defaultValues[domain][salesChannelId]) {
                        return Promise.resolve(defaultValues[domain][salesChannelId]);
                    }

                    return Promise.resolve({});
                }
            },
            repositoryFactory: {
                create: (entity) => ({
                    search: (criteria) => {
                        if (entity === 'sales_channel') {
                            return Promise.resolve(createEntityCollection([
                                {
                                    name: 'Storefront',
                                    translated: { name: 'Storefront' },
                                    id: uuid.get('storefront')
                                },
                                {
                                    name: 'Headless',
                                    translated: { name: 'Headless' },
                                    id: uuid.get('headless')
                                }
                            ]));
                        }

                        if (entity === 'product') {
                            return Promise.resolve([
                                {
                                    id: uuid.get('pullover'),
                                    name: 'Pullover'
                                },
                                {
                                    id: uuid.get('shirt'),
                                    name: 'Shirt'
                                }
                            ].filter(product => {
                                if (criteria.ids.length <= 0) {
                                    return true;
                                }

                                return criteria.ids.includes(product.id);
                            }));
                        }
                        if (entity === 'media') {
                            return Promise.resolve([
                                {
                                    hasFile: true,
                                    fileName: 'good-image',
                                    fileExtension: 'jpg',
                                    id: uuid.get('good-image')
                                },
                                {
                                    hasFile: true,
                                    fileName: 'funny-image',
                                    fileExtension: 'jpg',
                                    id: uuid.get('funny-image')
                                }
                            ]);
                        }

                        return Promise.resolve([]);
                    },
                    get: (id) => {
                        if (entity === 'product') {
                            if (id === uuid.get('pullover')) {
                                return Promise.resolve({
                                    id: uuid.get('pullover'),
                                    name: 'Pullover'
                                });
                            }

                            if (id === uuid.get('shirt')) {
                                return Promise.resolve({
                                    id: uuid.get('shirt'),
                                    name: 'Shirt'
                                });
                            }
                        }

                        if (entity === 'media') {
                            if (id === uuid.get('funny-image')) {
                                return Promise.resolve({
                                    hasFile: true,
                                    fileName: 'funny-image',
                                    fileExtension: 'jpg',
                                    id: uuid.get('funny-image')
                                });
                            }

                            if (id === uuid.get('good-image')) {
                                return Promise.resolve({
                                    hasFile: true,
                                    fileName: 'good-image',
                                    fileExtension: 'jpg',
                                    id: uuid.get('good-image')
                                });
                            }
                        }

                        return Promise.resolve({});
                    }
                })
            },
            validationService: {},
            mediaService: {}
        }
    });
}

function createConfig() {
    return [
        {
            name: null,
            title: { 'en-GB': 'First card' },
            elements: [
                {
                    name: 'ConfigRenderer.config.textField',
                    type: 'text',
                    config: {
                        label: {
                            'en-GB': 'text field'
                        },
                        defaultValue: 'Amazing field'
                    },
                    _test: {
                        domValueCheck: (field, domValue) => {
                            expect(field.find('input').element.value).toBe(domValue);
                        },
                        afterValue: 'Awesome field',
                        childValue: 'I am a child',
                        changeValueFunction: async (field, afterValue) => {
                            // change input value
                            await field.find('input[type="text"]').setValue(afterValue);
                        }
                    }
                },
                {
                    name: 'ConfigRenderer.config.textareaField',
                    type: 'textarea',
                    config: {
                        label: {
                            'en-GB': 'textarea field'
                        },
                        defaultValue: 'This is a textarea with much content.'
                    },
                    _test: {
                        domValueCheck: (field, domValue) => {
                            expect(field.find('textarea').element.value).toBe(domValue);
                        },
                        afterValue: 'We changed the textarea with much content.',
                        childValue: 'I am a child textarea field',
                        changeValueFunction: async (field, afterValue) => {
                            // change input value
                            await field.find('textarea').setValue(afterValue);
                        }
                    }
                },
                {
                    name: 'ConfigRenderer.config.urlField',
                    type: 'url',
                    config: {
                        defaultValue: 'https://www.shopware.com',
                        label: {
                            'en-GB': 'url field'
                        }
                    },
                    _test: {
                        defaultValueDom: 'www.shopware.com',
                        domValueCheck: (field, domValue) => {
                            expect(field.find('input').element.value).toBe(domValue);
                        },
                        afterValue: 'https://www.shopware.de',
                        afterValueDom: 'www.shopware.de',
                        childValue: 'https://www.child.shopware.com',
                        childValueDom: 'www.child.shopware.com',
                        changeValueFunction: async (field, afterValue) => {
                            // change input value
                            await field.find('input').setValue(afterValue);
                            await field.find('input').trigger('blur');
                        }
                    }
                },
                {
                    name: 'ConfigRenderer.config.passwordField',
                    type: 'password',
                    config: {
                        defaultValue: 'V3RY_S3CR3T',
                        label: {
                            'en-GB': 'password field'
                        }
                    },
                    _test: {
                        domValueCheck: (field, domValue) => {
                            expect(field.find('input').element.value).toBe(domValue);
                        },
                        afterValue: 'M0R3-S3CR3T_PA$$W0RD',
                        childValue: 'I-AM-A-CH!LD-VALU3',
                        changeValueFunction: async (field, afterValue) => {
                            // change input value
                            await field.find('input').setValue(afterValue);
                        }
                    }
                },
                {
                    name: 'ConfigRenderer.config.intField',
                    type: 'int',
                    config: {
                        defaultValue: 7,
                        label: {
                            'en-GB': 'int field'
                        }
                    },
                    _test: {
                        defaultValueDom: '7',
                        domValueCheck: (field, domValue) => {
                            expect(field.find('input').element.value).toBe(domValue);
                        },
                        afterValue: 42,
                        afterValueDom: '42',
                        childValue: 987,
                        childValueDom: '987',
                        fallbackValue: '0',
                        changeValueFunction: async (field, afterValue) => {
                            // change input value
                            await field.find('input[type="text"]').setValue(afterValue);
                            await field.find('input[type="text"]').trigger('change');
                        }
                    }
                },
                {
                    name: 'ConfigRenderer.config.floatField',
                    type: 'float',
                    config: {
                        defaultValue: 1.23,
                        label: {
                            'en-GB': 'float field'
                        }
                    },
                    _test: {
                        defaultValueDom: '1.23',
                        domValueCheck: (field, domValue) => {
                            expect(field.find('input').element.value).toBe(domValue);
                        },
                        afterValue: 420.55,
                        afterValueDom: '420.55',
                        childValue: 33.25,
                        childValueDom: '33.25',
                        fallbackValue: '0',
                        changeValueFunction: async (field, afterValue) => {
                            // change input value
                            await field.find('input[type="text"]').setValue(afterValue);
                            await field.find('input[type="text"]').trigger('change');
                        }
                    }
                },
                {
                    name: 'ConfigRenderer.config.boolField',
                    type: 'bool',
                    config: {
                        defaultValue: true,
                        label: {
                            'en-GB': 'bool field'
                        }
                    },
                    _test: {
                        domValueCheck: (field, domValue) => {
                            expect(field.find('input').element.checked).toBe(domValue);
                        },
                        afterValue: false,
                        childValue: false,
                        fallbackValue: false,
                        changeValueFunction: async (field) => {
                            // change input value
                            await field.find('input[type="checkbox"]').trigger('click');
                            await field.find('input[type="checkbox"]').trigger('change');
                        }
                    }
                },
                {
                    name: 'ConfigRenderer.config.checkboxField',
                    type: 'checkbox',
                    config: {
                        defaultValue: true,
                        label: {
                            'en-GB': 'checkbox field'
                        }
                    },
                    _test: {
                        domValueCheck: (field, domValue) => {
                            expect(field.find('input').element.checked).toBe(domValue);
                        },
                        afterValue: false,
                        childValue: false,
                        fallbackValue: false,
                        changeValueFunction: async (field) => {
                            // change input value
                            await field.find('input[type="checkbox"]').trigger('click');
                            await field.find('input[type="checkbox"]').trigger('change');
                        }
                    }
                },
                {
                    name: 'ConfigRenderer.config.datetimeField',
                    type: 'datetime',
                    config: {
                        defaultValue: '2000-01-01T12:00:00+00:00',
                        label: {
                            'en-GB': 'datetime field'
                        }
                    },
                    _test: {
                        domValueCheck: (field, domValue) => {
                            expect(field.find('input').element.value).toBe(domValue);
                        },
                        afterValue: '2222-01-01T16:00:00+00:00',
                        childValue: '2233-01-01T16:00:00+00:00',
                        changeValueFunction: async (field, afterValue) => {
                            // change input value
                            await field.find('input[type="text"]').setValue(afterValue);
                        }
                    }
                },
                {
                    name: 'ConfigRenderer.config.dateField',
                    type: 'date',
                    config: {
                        defaultValue: '2000-01-01T00:00:00+00:00',
                        label: {
                            'en-GB': 'date field'
                        }
                    },
                    _test: {
                        domValueCheck: (field, domValue) => {
                            expect(field.find('input').element.value).toBe(domValue);
                        },
                        afterValue: '2000-12-12T12:00:00+00:00',
                        childValue: '2020-12-12T12:00:00+00:00',
                        changeValueFunction: async (field, afterValue) => {
                            // change input value
                            await field.find('input[type="text"]').setValue(afterValue);
                        }
                    }
                },
                {
                    name: 'ConfigRenderer.config.timeField',
                    type: 'time',
                    config: {
                        defaultValue: '12:00:00+00:00',
                        label: {
                            'en-GB': 'time field'
                        }
                    },
                    _test: {
                        domValueCheck: (field, domValue) => {
                            expect(field.find('input').element.value).toBe(domValue);
                        },
                        afterValue: '18:00:00+00:00',
                        childValue: '13:00:00+00:00',
                        changeValueFunction: async (field, afterValue) => {
                            // change input value
                            await field.find('input[type="text"]').setValue(afterValue);
                        }
                    }
                },
                {
                    name: 'ConfigRenderer.config.colorpickerField',
                    type: 'colorpicker',
                    config: {
                        defaultValue: '#123abc',
                        label: {
                            'en-GB': 'colorpicker field'
                        }
                    },
                    _test: {
                        domValueCheck: (field, domValue) => {
                            expect(field.find('input').element.value).toBe(domValue);
                        },
                        afterValue: '#ccc444',
                        childValue: '#789ced',
                        changeValueFunction: async (field, afterValue) => {
                            // change input value
                            await field.find('input[type="text"]').setValue(afterValue);
                        }
                    }
                },
                {
                    name: 'ConfigRenderer.config.singleSelectField',
                    type: 'single-select',
                    config: {
                        defaultValue: 'blue',
                        label: {
                            'en-GB': 'single-select field'
                        },
                        options: [
                            {
                                id: 'yellow',
                                value: 'yellow',
                                name: {
                                    'en-GB': 'yellow'
                                }
                            },
                            {
                                id: 'blue',
                                value: 'blue',
                                name: {
                                    'en-GB': 'blue'
                                }
                            },
                            {
                                id: 'green',
                                value: 'green',
                                name: {
                                    'en-GB': 'green'
                                }
                            }
                        ]
                    },
                    _test: {
                        domValueCheck: (field, domValue) => {
                            expect(field.find('.sw-single-select__selection-text').text()).toBe(domValue);
                        },
                        afterValue: 'green',
                        childValue: 'yellow',
                        changeValueFunction: async (field, afterValue) => {
                            // open select field
                            await field.find('.sw-select__selection').trigger('click');

                            // find after value
                            const optionChoice = field.find(`.sw-select-option--${afterValue}`);
                            expect(optionChoice.isVisible()).toBe(true);

                            // click on second option
                            await optionChoice.trigger('click');
                        }
                    }
                },
                {
                    name: 'ConfigRenderer.config.multiSelectField',
                    type: 'multi-select',
                    config: {
                        defaultValue: ['blue'],
                        label: {
                            'en-GB': 'multi-select field'
                        },
                        options: [
                            {
                                id: 'yellow',
                                name: {
                                    'en-GB': 'yellow'
                                }
                            },
                            {
                                id: 'blue',
                                name: {
                                    'en-GB': 'blue'
                                }
                            },
                            {
                                id: 'green',
                                name: {
                                    'en-GB': 'green'
                                }
                            }
                        ]
                    },
                    _test: {
                        domValueCheck: (field, domValue) => {
                            expect(Array.isArray(domValue)).toBe(true);
                            domValue.forEach((value, index) => {
                                expect(field.find(`.sw-select-selection-list__item-holder--${index}`).text()).toBe(value);
                            });
                        },
                        afterValue: ['blue', 'green'],
                        childValue: ['blue', 'green'],
                        fallbackValue: [],
                        changeValueFunction: async (field) => {
                            // open select field
                            await field.find('.sw-select__selection').trigger('click');

                            // find third value
                            const optionChoice = field.find('.sw-select-option--2');
                            expect(optionChoice.isVisible()).toBe(true);

                            // click on third option
                            await optionChoice.trigger('click');
                        }
                    }
                },
                {
                    name: 'ConfigRenderer.config.entitySelectField',
                    config: {
                        defaultValue: uuid.get('pullover'),
                        componentName: 'sw-entity-single-select',
                        entity: 'product',
                        label: {
                            'en-GB': 'Choose a product'
                        }
                    },
                    _test: {
                        defaultValueDom: 'Pullover',
                        domValueCheck: async (field, domValue) => {
                            await wrapper.vm.$forceUpdate();
                            expect(field.find('.sw-entity-single-select__selection-text').text()).toBe(domValue);
                        },
                        afterValue: uuid.get('shirt'),
                        afterValueDom: 'Shirt',
                        childValue: uuid.get('shirt'),
                        childValueDom: 'Shirt',
                        changeValueFunction: async (field) => {
                            // open select field
                            await field.find('.sw-select__selection').trigger('click');
                            await wrapper.vm.$nextTick();

                            // find second value
                            const optionChoice = field.find('.sw-select-option--1');
                            expect(optionChoice.isVisible()).toBe(true);

                            // click on second option
                            await optionChoice.trigger('click');
                        }
                    }
                },
                {
                    name: 'ConfigRenderer.config.mediaField',
                    config: {
                        defaultValue: uuid.get('funny-image'),
                        componentName: 'sw-media-field',
                        label: {
                            'en-GB': 'Upload media or choose one from the media manager'
                        }
                    },
                    _test: {
                        defaultValueDom: 'funny-image.jpg',
                        domValueCheck: async (field, domValue) => {
                            await wrapper.vm.$forceUpdate();

                            if (domValue.length > 0) {
                                expect(field.find('.sw-media-base-item__name').text()).toBe(domValue);
                            } else {
                                expect(field.find('.sw-media-base-item__name').exists()).toBe(false);
                            }
                        },
                        afterValue: uuid.get('good-image'),
                        afterValueDom: 'good-image.jpg',
                        childValue: uuid.get('good-image'),
                        childValueDom: 'good-image.jpg',
                        changeValueFunction: async (field) => {
                            await field.find('.sw-media-field__toggle-button').trigger('click');
                            await wrapper.vm.$nextTick();
                            await field.find('.sw-media-field__suggestion-list-entry:first-child .sw-media-base-item').trigger('click');
                        }
                    }
                },
                {
                    name: 'ConfigRenderer.config.textEditorField',
                    config: {
                        defaultValue: '<p>I am a paragraph</p>',
                        componentName: 'sw-text-editor',
                        label: {
                            'en-GB': 'Write some nice text with WYSIWYG editor'
                        }
                    },
                    _test: {
                        // defaultValueDom: 'funny-image.jpg',
                        domValueCheck: async (field, domValue) => {
                            await wrapper.vm.$forceUpdate();
                            expect(field.find('input').element.value).toBe(domValue);
                        },
                        afterValue: '<p>Fresh and new</p>',
                        childValue: '<p>Children which is fresh and new</p>',
                        changeValueFunction: async (field, afterValue) => {
                            await field.find('input').setValue(afterValue);
                        }
                    }
                }
            ]
        }
    ];
}

function createEntityCollection(entities = []) {
    return new Shopware.Data.EntityCollection('collection', 'collection', {}, null, entities);
}

describe('src/app/component/form/sw-custom-field-set-renderer', () => {
    afterEach(async () => {
        if (wrapper) await wrapper.destroy();
    });

    it('should be a Vue.JS component', async () => {
        wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should show a select field for the sales channels', async () => {
        wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const salesChannelSwitch = wrapper.find('.sw-field[label="sw-settings.system-config.labelSalesChannelSelect"]');
        const selectionText = salesChannelSwitch.find('.sw-entity-single-select__selection-text');

        expect(selectionText.text()).toBe('sw-sales-channel-switch.labelDefaultOption');
    });

    it('should change the sales channel', async () => {
        wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const salesChannelSwitch = wrapper.find('.sw-field[label="sw-settings.system-config.labelSalesChannelSelect"]');
        let selectionText = salesChannelSwitch.find('.sw-entity-single-select__selection-text');

        expect(selectionText.text()).toBe('sw-sales-channel-switch.labelDefaultOption');

        // open salesChannel switch field
        await salesChannelSwitch.find('.sw-select__selection').trigger('click');
        await wrapper.vm.$nextTick();

        // select headless sales channel
        const selectOptionTwo = salesChannelSwitch.find('.sw-select-option--2');
        expect(selectOptionTwo.text()).toBe('Headless');
        await selectOptionTwo.trigger('click');

        selectionText = salesChannelSwitch.find('.sw-entity-single-select__selection-text');
        expect(selectionText.text()).toBe('Headless');
    });

    createConfig()[0].elements.forEach(({
        name,
        type,
        config,
        _test
    }) => {
        it(`should render field with type "${type || name}" with the default value and should be able to change it`, async () => {
            const domValue = _test.defaultValueDom || config.defaultValue;
            const afterValueDom = _test.afterValueDom || _test.afterValue;

            wrapper = await createWrapper({
                'ConfigRenderer.config': {
                    null: {
                        [name]: config.defaultValue
                    }
                }
            });

            await flushPromises();

            // check if value in dom is right
            let field = wrapper.find(`.sw-system-config--field-${kebabCase(name)}`);
            await _test.domValueCheck(field, domValue);

            // check if value in actualConfigData is right
            expect(wrapper.vm.actualConfigData.null[name]).toEqual(config.defaultValue);

            // change value
            await _test.changeValueFunction(field, _test.afterValue);

            // check if new value in dom is visible
            field = wrapper.find(`.sw-system-config--field-${kebabCase(name)}`);
            await _test.domValueCheck(field, afterValueDom);

            // check if new value in actualConfigData is right
            expect(wrapper.vm.actualConfigData.null[name]).toEqual(_test.afterValue);
        });

        it(`should render field with type "${type || name}" with the inherit value and should be able to remove the inheritance`, async () => {
            const domValue = _test.defaultValueDom || config.defaultValue;

            wrapper = await createWrapper({
                'ConfigRenderer.config': {
                    null: {
                        [name]: config.defaultValue
                    }
                }
            });

            await flushPromises();

            const salesChannelSwitch = wrapper.find('.sw-field[label="sw-settings.system-config.labelSalesChannelSelect"]');
            let selectionText = salesChannelSwitch.find('.sw-entity-single-select__selection-text');

            expect(selectionText.text()).toBe('sw-sales-channel-switch.labelDefaultOption');

            // open salesChannel switch field
            await salesChannelSwitch.find('.sw-select__selection').trigger('click');
            await wrapper.vm.$nextTick();

            // select headless sales channel
            const selectOptionTwo = salesChannelSwitch.find('.sw-select-option--2');
            expect(selectOptionTwo.text()).toBe('Headless');
            await selectOptionTwo.trigger('click');
            await flushPromises();

            // check if headless sales channel is activated
            selectionText = salesChannelSwitch.find('.sw-entity-single-select__selection-text');
            expect(selectionText.text()).toBe('Headless');

            // check if value in dom shows the inherit value
            let field = wrapper.find(`.sw-system-config--field-${kebabCase(name)}`);
            await _test.domValueCheck(field, domValue);

            // check if value in actualConfigData is right (null or undefined)
            expect(wrapper.vm.actualConfigData[uuid.get('headless')][name]).toEqual(undefined);

            // check if inheritance switch is visible
            let inheritanceSwitch = field.find('.sw-inheritance-switch');
            expect(inheritanceSwitch.isVisible()).toBe(true);

            // check if switch show inheritance
            expect(inheritanceSwitch.classes()).toContain('sw-inheritance-switch--is-inherited');

            // remove inheritance
            await inheritanceSwitch.find('.sw-icon').trigger('click');

            // check if inheritance switch is not inherit anymore
            field = wrapper.find(`.sw-system-config--field-${kebabCase(name)}`);
            inheritanceSwitch = field.find('.sw-inheritance-switch');
            expect(inheritanceSwitch.classes()).toContain('sw-inheritance-switch--is-not-inherited');

            // check if child gets parent value
            field = wrapper.find(`.sw-system-config--field-${kebabCase(name)}`);
            await _test.domValueCheck(field, domValue);

            // check if value in actualConfigData is right (parent value)
            expect(wrapper.vm.actualConfigData[uuid.get('headless')][name]).toEqual(config.defaultValue);
        });

        it(`should render field with type "${type || name}" with the his value and should be able to restore parent value (when parent has value)`, async () => {
            const domValue = _test.defaultValueDom || config.defaultValue;
            const childValue = _test.childValue;
            const childValueDom = _test.childValueDom || childValue;

            wrapper = await createWrapper({
                'ConfigRenderer.config': {
                    [uuid.get('headless')]: {
                        [name]: childValue
                    },
                    null: {
                        [name]: config.defaultValue
                    }
                }
            });

            await flushPromises();

            const salesChannelSwitch = wrapper.find('.sw-field[label="sw-settings.system-config.labelSalesChannelSelect"]');
            let selectionText = salesChannelSwitch.find('.sw-entity-single-select__selection-text');

            expect(selectionText.text()).toBe('sw-sales-channel-switch.labelDefaultOption');

            // open salesChannel switch field
            await salesChannelSwitch.find('.sw-select__selection').trigger('click');
            await wrapper.vm.$nextTick();

            // select headless sales channel
            const selectOptionTwo = salesChannelSwitch.find('.sw-select-option--2');
            expect(selectOptionTwo.text()).toBe('Headless');
            await selectOptionTwo.trigger('click');
            await flushPromises();

            // check if headless sales channel is activated
            selectionText = salesChannelSwitch.find('.sw-entity-single-select__selection-text');
            expect(selectionText.text()).toBe('Headless');

            // check if value in dom shows the direct value
            let field = wrapper.find(`.sw-system-config--field-${kebabCase(name)}`);
            await _test.domValueCheck(field, childValueDom);

            // check if value in actualConfigData is right (null or undefined)
            expect(wrapper.vm.actualConfigData[uuid.get('headless')][name]).toEqual(childValue);

            // check if inheritance switch is visible
            let inheritanceSwitch = field.find('.sw-inheritance-switch');
            expect(inheritanceSwitch.isVisible()).toBe(true);

            // check if switch show inheritance
            expect(inheritanceSwitch.classes()).toContain('sw-inheritance-switch--is-not-inherited');

            // restore inheritance
            await inheritanceSwitch.find('.sw-icon').trigger('click');

            // check if inheritance switch is not inherit anymore
            field = wrapper.find(`.sw-system-config--field-${kebabCase(name)}`);
            inheritanceSwitch = field.find('.sw-inheritance-switch');
            expect(inheritanceSwitch.classes()).toContain('sw-inheritance-switch--is-inherited');

            // check if child gets parent value
            field = wrapper.find(`.sw-system-config--field-${kebabCase(name)}`);
            await _test.domValueCheck(field, domValue);

            // check if value in actualConfigData is null to inherit value from parent
            expect(wrapper.vm.actualConfigData[uuid.get('headless')][name]).toEqual(null);
        });

        it(`should render field with type "${type || name}" with the his value and should be able to restore parent value (when parent has no value)`, async () => {
            const childValue = _test.childValue;
            const childValueDom = _test.childValueDom || childValue;
            const fallbackValue = _test.hasOwnProperty('fallbackValue') ? _test.fallbackValue : '';

            wrapper = await createWrapper({
                'ConfigRenderer.config': {
                    [uuid.get('headless')]: {
                        [name]: childValue
                    },
                    null: {}
                }
            });

            await flushPromises();

            const salesChannelSwitch = wrapper.find('.sw-field[label="sw-settings.system-config.labelSalesChannelSelect"]');
            let selectionText = salesChannelSwitch.find('.sw-entity-single-select__selection-text');

            expect(selectionText.text()).toBe('sw-sales-channel-switch.labelDefaultOption');

            // open salesChannel switch field
            await salesChannelSwitch.find('.sw-select__selection').trigger('click');
            await wrapper.vm.$nextTick();

            // select headless sales channel
            const selectOptionTwo = salesChannelSwitch.find('.sw-select-option--2');
            expect(selectOptionTwo.text()).toBe('Headless');
            await selectOptionTwo.trigger('click');
            await flushPromises();

            // check if headless sales channel is activated
            selectionText = salesChannelSwitch.find('.sw-entity-single-select__selection-text');
            expect(selectionText.text()).toBe('Headless');

            // check if value in dom shows the direct value
            let field = wrapper.find(`.sw-system-config--field-${kebabCase(name)}`);
            await _test.domValueCheck(field, childValueDom);

            // check if value in actualConfigData is right (null or undefined)
            expect(wrapper.vm.actualConfigData[uuid.get('headless')][name]).toEqual(childValue);

            // check if inheritance switch is visible
            let inheritanceSwitch = field.find('.sw-inheritance-switch');
            expect(inheritanceSwitch.isVisible()).toBe(true);

            // check if switch show inheritance
            expect(inheritanceSwitch.classes()).toContain('sw-inheritance-switch--is-not-inherited');

            // restore inheritance
            await inheritanceSwitch.find('.sw-icon').trigger('click');

            // check if inheritance switch is not inherit anymore
            field = wrapper.find(`.sw-system-config--field-${kebabCase(name)}`);
            inheritanceSwitch = field.find('.sw-inheritance-switch');
            expect(inheritanceSwitch.classes()).toContain('sw-inheritance-switch--is-inherited');

            // check if child gets fallback parent value
            field = wrapper.find(`.sw-system-config--field-${kebabCase(name)}`);
            await _test.domValueCheck(field, fallbackValue);

            // check if value in actualConfigData is null to inherit value from parent
            expect(wrapper.vm.actualConfigData[uuid.get('headless')][name]).toEqual(null);
        });
    });
});
