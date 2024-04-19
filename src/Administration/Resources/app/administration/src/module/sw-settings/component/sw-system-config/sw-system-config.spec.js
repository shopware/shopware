/**
 * @package system-settings
 */
/* eslint-disable max-len */
import { mount } from '@vue/test-utils';
import ShopwareError from 'src/core/data/ShopwareError';
import { kebabCase } from 'lodash';
import uuid from 'src/../test/_helper_/uuid';
import 'src/app/filter/media-name.filter';
import 'src/app/filter/unicode-uri';

/** @type Wrapper */
let wrapper;

async function createWrapper(defaultValues = {}) {
    return mount(await wrapTestComponent('sw-system-config'), {
        props: {
            salesChannelSwitchable: true,
            domain: 'ConfigRenderer.config',
        },
        global: {
            directives: {
                tooltip: {},
                popover: {},
            },
            renderStubDefaultSlot: true,
            stubs: {
                'sw-form-field-renderer': await wrapTestComponent('sw-form-field-renderer'),
                'sw-card': await wrapTestComponent('sw-card'),
                'sw-card-deprecated': await wrapTestComponent('sw-card-deprecated'),
                'sw-ignore-class': true,
                'sw-sales-channel-switch': await wrapTestComponent('sw-sales-channel-switch'),
                'sw-entity-single-select': await wrapTestComponent('sw-entity-single-select'),
                'sw-button': await wrapTestComponent('sw-button'),
                'sw-button-deprecated': await wrapTestComponent('sw-button-deprecated'),
                'sw-label': await wrapTestComponent('sw-label'),
                'sw-inherit-wrapper': await wrapTestComponent('sw-inherit-wrapper'),
                'sw-inheritance-switch': await wrapTestComponent('sw-inheritance-switch'),
                'sw-text-field': await wrapTestComponent('sw-text-field'),
                'sw-text-field-deprecated': await wrapTestComponent('sw-text-field-deprecated', { sync: true }),
                'sw-password-field': await wrapTestComponent('sw-password-field'),
                'sw-textarea-field': await wrapTestComponent('sw-textarea-field'),
                'sw-contextual-field': await wrapTestComponent('sw-contextual-field'),
                'sw-switch-field': await wrapTestComponent('sw-switch-field'),
                'sw-switch-field-deprecated': await wrapTestComponent('sw-switch-field-deprecated', { sync: true }),
                'sw-number-field': await wrapTestComponent('sw-number-field'),
                'sw-number-field-deprecated': await wrapTestComponent('sw-number-field-deprecated', { sync: true }),
                'sw-checkbox-field': await wrapTestComponent('sw-checkbox-field'),
                'sw-block-field': await wrapTestComponent('sw-block-field'),
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-field-error': await wrapTestComponent('sw-field-error'),
                'sw-icon': {
                    template: '<div class="sw-icon" @click="$emit(\'click\')"></div>',
                },
                'sw-single-select': await wrapTestComponent('sw-single-select'),
                'sw-multi-select': await wrapTestComponent('sw-multi-select'),
                'sw-entity-multi-select': await wrapTestComponent('sw-entity-multi-select'),
                'sw-entity-multi-id-select': await wrapTestComponent('sw-entity-multi-id-select'),
                'sw-select-base': await wrapTestComponent('sw-select-base'),
                'sw-select-result-list': await wrapTestComponent('sw-select-result-list'),
                'sw-select-result': await wrapTestComponent('sw-select-result'),
                'sw-select-selection-list': await wrapTestComponent('sw-select-selection-list'),
                'sw-popover': await wrapTestComponent('sw-popover'),
                'sw-highlight-text': await wrapTestComponent('sw-highlight-text'),
                'sw-media-field': await wrapTestComponent('sw-media-field'),
                'sw-url-field': await wrapTestComponent('sw-url-field'),
                'sw-media-media-item': await wrapTestComponent('sw-media-media-item'),
                'sw-media-base-item': await wrapTestComponent('sw-media-base-item'),
                'sw-media-preview-v2': await wrapTestComponent('sw-media-preview-v2'),
                'sw-colorpicker': await wrapTestComponent('sw-text-field'),
                'sw-upload-listener': true,
                'sw-simple-search-field': true,
                'sw-loader': true,
                'sw-datepicker': await wrapTestComponent('sw-text-field'),
                'sw-text-editor': await wrapTestComponent('sw-text-field'),
                'sw-extension-component-section': true,
                'sw-ai-copilot-badge': true,
            },
            provide: {
                systemConfigApiService: {
                    getConfig: () => Promise.resolve(createConfig()),
                    getValues: (domain, salesChannelId) => {
                        if (defaultValues[domain] && defaultValues[domain][salesChannelId]) {
                            return Promise.resolve(defaultValues[domain][salesChannelId]);
                        }

                        return Promise.resolve({});
                    },
                },
                repositoryFactory: {
                    create: (entity) => ({
                        search: (criteria) => {
                            if (entity === 'sales_channel') {
                                return Promise.resolve(createEntityCollection([
                                    {
                                        name: 'Storefront',
                                        translated: { name: 'Storefront' },
                                        id: uuid.get('storefront'),
                                    },
                                    {
                                        name: 'Headless',
                                        translated: { name: 'Headless' },
                                        id: uuid.get('headless'),
                                    },
                                ]));
                            }

                            if (entity === 'product') {
                                return Promise.resolve([
                                    {
                                        id: uuid.get('pullover'),
                                        name: 'Pullover',
                                    },
                                    {
                                        id: uuid.get('shirt'),
                                        name: 'Shirt',
                                    },
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
                                        id: uuid.get('good-image'),
                                    },
                                    {
                                        hasFile: true,
                                        fileName: 'funny-image',
                                        fileExtension: 'jpg',
                                        id: uuid.get('funny-image'),
                                    },
                                ]);
                            }

                            return Promise.resolve([]);
                        },
                        get: (id) => {
                            if (entity === 'product') {
                                if (id === uuid.get('pullover')) {
                                    return Promise.resolve({
                                        id: uuid.get('pullover'),
                                        name: 'Pullover',
                                    });
                                }

                                if (id === uuid.get('shirt')) {
                                    return Promise.resolve({
                                        id: uuid.get('shirt'),
                                        name: 'Shirt',
                                    });
                                }
                            }

                            if (entity === 'media') {
                                if (id === uuid.get('funny-image')) {
                                    return Promise.resolve({
                                        hasFile: true,
                                        fileName: 'funny-image',
                                        fileExtension: 'jpg',
                                        id: uuid.get('funny-image'),
                                    });
                                }

                                if (id === uuid.get('good-image')) {
                                    return Promise.resolve({
                                        hasFile: true,
                                        fileName: 'good-image',
                                        fileExtension: 'jpg',
                                        id: uuid.get('good-image'),
                                    });
                                }
                            }

                            return Promise.resolve({});
                        },
                    }),
                },
                validationService: {},
                mediaService: {},
            },
        },
    });
}

function createConfig() {
    const firstCardElements = [
        {
            name: 'ConfigRenderer.config.textField',
            type: 'text',
            config: {
                required: true,
                label: {
                    'en-GB': 'text field',
                },
                defaultValue: 'Amazing field',
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
                },
            },
        },
        {
            name: 'ConfigRenderer.config.textareaField',
            type: 'textarea',
            config: {
                label: {
                    'en-GB': 'textarea field',
                },
                defaultValue: 'This is a textarea with much content.',
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
                },
            },
        },
        {
            name: 'ConfigRenderer.config.urlField',
            type: 'url',
            config: {
                defaultValue: 'https://www.shopware.com',
                label: {
                    'en-GB': 'url field',
                },
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
                    await flushPromises();
                },
            },
        },
        {
            name: 'ConfigRenderer.config.passwordField',
            type: 'password',
            config: {
                defaultValue: 'V3RY_S3CR3T',
                label: {
                    'en-GB': 'password field',
                },
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
                },
            },
        },
        {
            name: 'ConfigRenderer.config.intField',
            type: 'int',
            config: {
                defaultValue: 7,
                label: {
                    'en-GB': 'int field',
                },
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
                },
            },
        },
        {
            name: 'ConfigRenderer.config.floatField',
            type: 'float',
            config: {
                defaultValue: 1.23,
                label: {
                    'en-GB': 'float field',
                },
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
                },
            },
        },
        {
            name: 'ConfigRenderer.config.boolField',
            type: 'bool',
            config: {
                defaultValue: true,
                label: {
                    'en-GB': 'bool field',
                },
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
                },
            },
        },
        {
            name: 'ConfigRenderer.config.checkboxField',
            type: 'checkbox',
            config: {
                defaultValue: true,
                label: {
                    'en-GB': 'checkbox field',
                },
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
                },
            },
        },
        {
            name: 'ConfigRenderer.config.datetimeField',
            type: 'datetime',
            config: {
                defaultValue: '2000-01-01T12:00:00+00:00',
                label: {
                    'en-GB': 'datetime field',
                },
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
                },
            },
        },
        {
            name: 'ConfigRenderer.config.dateField',
            type: 'date',
            config: {
                defaultValue: '2000-01-01T00:00:00+00:00',
                label: {
                    'en-GB': 'date field',
                },
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
                },
            },
        },
        {
            name: 'ConfigRenderer.config.timeField',
            type: 'time',
            config: {
                defaultValue: '12:00:00+00:00',
                label: {
                    'en-GB': 'time field',
                },
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
                },
            },
        },
        {
            name: 'ConfigRenderer.config.colorpickerField',
            type: 'colorpicker',
            config: {
                defaultValue: '#123abc',
                label: {
                    'en-GB': 'colorpicker field',
                },
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
                },
            },
        },
        {
            name: 'ConfigRenderer.config.singleSelectField',
            type: 'single-select',
            config: {
                defaultValue: 'blue',
                label: {
                    'en-GB': 'single-select field',
                },
                options: [
                    {
                        id: 'yellow',
                        value: 'yellow',
                        name: {
                            'en-GB': 'yellow',
                        },
                    },
                    {
                        id: 'blue',
                        value: 'blue',
                        name: {
                            'en-GB': 'blue',
                        },
                    },
                    {
                        id: 'green',
                        value: 'green',
                        name: {
                            'en-GB': 'green',
                        },
                    },
                ],
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
                    await flushPromises();

                    // find after value
                    const optionChoice = field.find(`.sw-select-option--${afterValue}`);
                    expect(optionChoice.isVisible()).toBe(true);

                    // click on second option
                    await optionChoice.trigger('click');
                },
            },
        },
        {
            name: 'ConfigRenderer.config.multiSelectField',
            type: 'multi-select',
            config: {
                defaultValue: ['blue'],
                label: {
                    'en-GB': 'multi-select field',
                },
                options: [
                    {
                        id: 'yellow',
                        name: {
                            'en-GB': 'yellow',
                        },
                    },
                    {
                        id: 'blue',
                        name: {
                            'en-GB': 'blue',
                        },
                    },
                    {
                        id: 'green',
                        name: {
                            'en-GB': 'green',
                        },
                    },
                ],
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
                    await flushPromises();

                    // find third value
                    const optionChoice = field.find('.sw-select-option--2');
                    expect(optionChoice.isVisible()).toBe(true);

                    // click on third option
                    await optionChoice.trigger('click');
                },
            },
        },
        {
            name: 'ConfigRenderer.config.entitySelectField',
            config: {
                defaultValue: uuid.get('pullover'),
                componentName: 'sw-entity-single-select',
                entity: 'product',
                label: {
                    'en-GB': 'Choose a product',
                },
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
                    await flushPromises();

                    // find second value
                    const optionChoice = field.find('.sw-select-option--1');
                    expect(optionChoice.isVisible()).toBe(true);

                    // click on second option
                    await optionChoice.trigger('click');
                },
            },
        },
        {
            name: 'ConfigRenderer.config.mediaField',
            config: {
                defaultValue: uuid.get('funny-image'),
                componentName: 'sw-media-field',
                label: {
                    'en-GB': 'Upload media or choose one from the media manager',
                },
            },
            _test: {
                defaultValueDom: 'funny-image.jpg',
                domValueCheck: async (field, domValue) => {
                    await wrapper.vm.$forceUpdate();
                    await flushPromises();

                    if (domValue.length > 0) {
                        // TODO: this is not working
                        expect(
                            field.find('.sw-media-base-item__name')
                                .text(),
                        ).toBe(domValue);
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
                    await flushPromises();

                    await field
                        .find('.sw-media-field__suggestion-list-entry:first-child .sw-media-base-item')
                        .trigger('click');
                },
            },
        },
        {
            name: 'ConfigRenderer.config.textEditorField',
            config: {
                defaultValue: '<p>I am a paragraph</p>',
                componentName: 'sw-text-editor',
                label: {
                    'en-GB': 'Write some nice text with WYSIWYG editor',
                },
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
                },
            },
        },
    ];

    return [
        {
            name: null,
            title: { 'en-GB': 'First card' },
            elements: firstCardElements,
        },
        {
            name: null,
            title: { 'en-GB': 'Card with AI badge' },
            elements: [],
            aiBadge: true,
        },
    ];
}

function createEntityCollection(entities = []) {
    return new Shopware.Data.EntityCollection('collection', 'collection', {}, null, entities);
}

describe('src/module/sw-settings/component/sw-system-config/sw-system-config', () => {
    it('should be a Vue.JS component', async () => {
        wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should show a select field for the sales channels', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        const selectionText = wrapper.find('#salesChannelSelect .sw-entity-single-select__selection-text');

        expect(selectionText.text()).toBe('sw-sales-channel-switch.labelDefaultOption');
    });

    it('should change the sales channel', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        let salesChannelSwitch = wrapper.find('.sw-field[label="sw-settings.system-config.labelSalesChannelSelect"]');
        let selectionText = salesChannelSwitch.find('.sw-entity-single-select__selection-text');

        expect(selectionText.text()).toBe('sw-sales-channel-switch.labelDefaultOption');

        // open salesChannel switch field
        await salesChannelSwitch.find('.sw-select__selection')
            .trigger('click');
        await flushPromises();

        salesChannelSwitch = wrapper.find('.sw-field[label="sw-settings.system-config.labelSalesChannelSelect"]');

        // select headless sales channel
        const selectOptionTwo = salesChannelSwitch.find('.sw-select-option--2');
        expect(selectOptionTwo.text()).toBe('Headless');
        await selectOptionTwo.trigger('click');

        selectionText = salesChannelSwitch.find('.sw-entity-single-select__selection-text');
        expect(selectionText.text()).toBe('Headless');
    });

    it('should return ShopwareError when has error', async () => {
        await Shopware.State.dispatch('error/addApiError', {
            expression: 'SYSTEM_CONFIG.null.dummyKey',
            error: new ShopwareError({ code: 'dummyCode' }),
        });

        wrapper = await createWrapper({
            SYSTEM_CONFIG: {
                null: {
                    dummyKey: 'Default value',
                },
            },
        });

        const error = wrapper.vm.getFieldError('dummyKey');

        expect(error).toBeInstanceOf(ShopwareError);
    });

    createConfig()[0].elements.forEach(({
        name,
        type,
        config,
        _test,
    }) => {
        it(`should render field with type "${type || name}" with the default value and should be able to change it`, async () => {
            const domValue = _test.defaultValueDom || config.defaultValue;
            const afterValueDom = _test.afterValueDom || _test.afterValue;

            wrapper = await createWrapper({
                'ConfigRenderer.config': {
                    null: {
                        [name]: config.defaultValue,
                    },
                },
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
                        [name]: config.defaultValue,
                    },
                },
            });

            await flushPromises();

            const salesChannelSwitch = wrapper.find('.sw-field[label="sw-settings.system-config.labelSalesChannelSelect"]');
            let selectionText = salesChannelSwitch.find('.sw-entity-single-select__selection-text');

            expect(selectionText.text()).toBe('sw-sales-channel-switch.labelDefaultOption');

            // open salesChannel switch field
            await salesChannelSwitch.find('.sw-select__selection').trigger('click');
            await flushPromises();

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
            let inheritanceSwitch = field.find('.sw-inheritance-switch');

            // check if switch show inheritance
            expect(inheritanceSwitch.classes()).toContain('sw-inheritance-switch--is-inherited');

            // check if inheritance switch is visible
            inheritanceSwitch = field.find('.sw-inheritance-switch');
            expect(inheritanceSwitch.isVisible()).toBe(true);

            // check if value in actualConfigData is right (null or undefined)
            expect(wrapper.vm.actualConfigData[uuid.get('headless')][name]).toBeUndefined();

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
                        [name]: childValue,
                    },
                    null: {
                        [name]: config.defaultValue,
                    },
                },
            });

            await flushPromises();

            const salesChannelSwitch = wrapper.find('.sw-field[label="sw-settings.system-config.labelSalesChannelSelect"]');
            let selectionText = salesChannelSwitch.find('.sw-entity-single-select__selection-text');

            expect(selectionText.text()).toBe('sw-sales-channel-switch.labelDefaultOption');

            // open salesChannel switch field
            await salesChannelSwitch.find('.sw-select__selection').trigger('click');
            await flushPromises();

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
            await flushPromises();

            // check if inheritance switch is not inherit anymore
            field = wrapper.find(`.sw-system-config--field-${kebabCase(name)}`);
            inheritanceSwitch = field.find('.sw-inheritance-switch');
            expect(inheritanceSwitch.classes()).toContain('sw-inheritance-switch--is-inherited');

            // check if child gets parent value
            field = wrapper.find(`.sw-system-config--field-${kebabCase(name)}`);
            await _test.domValueCheck(field, domValue);

            // check if value in actualConfigData is null to inherit value from parent
            expect(wrapper.vm.actualConfigData[uuid.get('headless')][name]).toBeNull();
        });

        it(`should render field with type "${type || name}" with the his value and should be able to restore parent value (when parent has no value)`, async () => {
            const childValue = _test.childValue;
            const childValueDom = _test.childValueDom || childValue;
            const fallbackValue = _test.hasOwnProperty('fallbackValue') ? _test.fallbackValue : '';

            wrapper = await createWrapper({
                'ConfigRenderer.config': {
                    [uuid.get('headless')]: {
                        [name]: childValue,
                    },
                    null: {},
                },
            });

            await flushPromises();

            const salesChannelSwitch = wrapper.find('.sw-field[label="sw-settings.system-config.labelSalesChannelSelect"]');
            let selectionText = salesChannelSwitch.find('.sw-entity-single-select__selection-text');

            expect(selectionText.text()).toBe('sw-sales-channel-switch.labelDefaultOption');

            // open salesChannel switch field
            await salesChannelSwitch.find('.sw-select__selection').trigger('click');
            await flushPromises();

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
            expect(wrapper.vm.actualConfigData[uuid.get('headless')][name]).toBeNull();
        });
    });

    it('should contain ai badge in second card', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.sw-system-config__card--0 sw-ai-copilot-badge-stub').exists()).toBe(false);
        expect(wrapper.find('.sw-system-config__card--1 sw-ai-copilot-badge-stub').exists()).toBe(true);
    });

    it('should reinitialize on domain change', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        const createdSpy = jest.spyOn(wrapper.vm, 'createdComponent');

        await wrapper.setProps({
            domain: 'jest.test',
        });

        expect(createdSpy).toHaveBeenCalled();
    });
});
