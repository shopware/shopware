/* eslint-disable sw-test-rules/test-file-max-lines-warning */

/**
 * @sw-package inventory
 */
import { flushPromises, mount } from '@vue/test-utils';
import { nextTick } from 'vue';
import { MtTextField } from '@shopware-ag/meteor-component-library';

const packagingItemClassName = [
    '.sw-product-packaging-form__purchase-unit-field',
    '.sw-select-product__select_unit',
    '.sw-product-packaging-form__pack-unit-field',
    '.sw-product-packaging-form__pack-unit-plural-field',
    '.sw-product-packaging-form__reference-unit-field',
];

function createEntityCollection(entities = []) {
    return new Shopware.Data.EntityCollection('collection', 'collection', {}, null, entities);
}

function createProductRepositoryOptions(productRepositoryGet) {
    return {
        repositoryFactoryCreate: (entity) => {
            if (entity !== 'product') {
                return null;
            }

            return {
                get: productRepositoryGet,
                search: jest.fn(),
            };
        },
    };
}

function createTranslatedCustomField({ name, type, customFieldType, inputType, label }) {
    return {
        name,
        type,
        config: {
            componentName: 'sw-field',
            customFieldType,
            type: inputType,
            label: {
                'en-GB': label,
                'de-DE': label,
            },
        },
    };
}

function createTranslatedTextField(name = 'custom_test_text', label = 'Text example') {
    return createTranslatedCustomField({
        name,
        type: 'text',
        customFieldType: 'text',
        inputType: 'text',
        label,
    });
}

function createTranslatedCheckboxField(name = 'custom_test_checkbox', label = 'Checkbox example') {
    return createTranslatedCustomField({
        name,
        type: 'bool',
        customFieldType: 'checkbox',
        inputType: 'checkbox',
        label,
    });
}

function createCustomFieldSets(customFields) {
    return createEntityCollection([
        {
            id: 'set-1',
            name: 'product_specs',
            position: 1,
            config: {
                label: {
                    'en-GB': 'Specifications',
                    'de-DE': 'Spezifikationen',
                },
            },
            customFields,
        },
    ]);
}

function createProductEntity({ id = 'product-id', customFields = null, translatedCustomFields = {} } = {}) {
    return {
        id,
        isNew: () => false,
        getEntityName: () => 'product',
        customFields,
        translated: {
            customFields: translatedCustomFields,
        },
        customFieldSetSelectionActive: null,
        customFieldSets: createEntityCollection(),
    };
}

async function withTranslatedLanguageContext(
    { languageId = 'de-DE', systemLanguageId = 'en-GB', parentId = 'en-GB' } = {},
    callback,
) {
    const previousLanguageId = Shopware.Context.api.languageId;
    const previousSystemLanguageId = Shopware.Context.api.systemLanguageId;
    const previousLanguage = Shopware.Store.get('context').api.language;

    Shopware.Context.api.languageId = languageId;
    Shopware.Context.api.systemLanguageId = systemLanguageId;
    Shopware.Store.get('context').api.language = {
        id: languageId,
        parentId,
    };

    try {
        await callback();
    } finally {
        Shopware.Store.get('context').api.language = previousLanguage;
        Shopware.Context.api.languageId = previousLanguageId;
        Shopware.Context.api.systemLanguageId = previousSystemLanguageId;
    }
}

function setupCustomFieldSpecificationState({ product, parentProduct = {}, customFieldSets }) {
    const store = Shopware.Store.get('swProductDetail');

    store.product = product;
    store.parentProduct = parentProduct;
    store.customFieldSets = customFieldSets;
}

async function createWrapper(privileges = [], options = {}) {
    const { renderRealCustomFieldRenderer = false, repositoryFactoryCreate } = options;

    const stubs = {
        'mt-card': {
            template: '<div class="mt-card"><slot></slot></div>',
        },
        'sw-product-measurement-form': await wrapTestComponent('sw-product-measurement-form', { sync: true }),
        'sw-product-packaging-form': await wrapTestComponent('sw-product-packaging-form', { sync: true }),
        'sw-product-properties': true,
        'sw-product-feature-set-form': true,
        'sw-custom-field-set-renderer': renderRealCustomFieldRenderer
            ? await wrapTestComponent('sw-custom-field-set-renderer', { sync: true })
            : true,
        'sw-container': await wrapTestComponent('sw-container'),
        'sw-inherit-wrapper': await wrapTestComponent('sw-inherit-wrapper', { sync: true }),
        'sw-text-field': true,
        'sw-text-editor': true,
        'sw-entity-single-select': true,
        'sw-skeleton': true,
        'sw-help-text': true,
        'sw-inheritance-switch': true,
        'mt-unit-field': true,
        'i18n-t': {
            template: '<div class="i18n-stub"><slot></slot></div>',
        },
        'sw-internal-link': true,
    };

    if (renderRealCustomFieldRenderer) {
        Object.assign(stubs, {
            'sw-label': await wrapTestComponent('sw-label'),
            'sw-tabs': await wrapTestComponent('sw-tabs'),
            'sw-tabs-deprecated': await wrapTestComponent('sw-tabs-deprecated', { sync: true }),
            'sw-tabs-item': await wrapTestComponent('sw-tabs-item'),
            'sw-form-field-renderer': await wrapTestComponent('sw-form-field-renderer', { sync: true }),
            'sw-text-field': await wrapTestComponent('sw-text-field'),
            'sw-text-field-deprecated': await wrapTestComponent('sw-text-field-deprecated', { sync: true }),
            'sw-contextual-field': await wrapTestComponent('sw-contextual-field'),
            'sw-number-field': await wrapTestComponent('sw-number-field'),
            'sw-number-field-deprecated': await wrapTestComponent('sw-number-field-deprecated', { sync: true }),
            'sw-checkbox-field': await wrapTestComponent('sw-checkbox-field'),
            'sw-checkbox-field-deprecated': await wrapTestComponent('sw-checkbox-field-deprecated', { sync: true }),
            'sw-entity-multi-select': true,
            'sw-block-field': await wrapTestComponent('sw-block-field', { sync: true }),
            'sw-base-field': await wrapTestComponent('sw-base-field', { sync: true }),
            'sw-field-error': await wrapTestComponent('sw-field-error'),
            'sw-single-select': await wrapTestComponent('sw-single-select'),
            'sw-multi-select': await wrapTestComponent('sw-multi-select'),
            'sw-select-base': await wrapTestComponent('sw-select-base'),
            'sw-select-result-list': await wrapTestComponent('sw-select-result-list'),
            'sw-select-result': await wrapTestComponent('sw-select-result'),
            'sw-select-selection-list': await wrapTestComponent('sw-select-selection-list'),
            'sw-popover': await wrapTestComponent('sw-popover'),
            'sw-popover-deprecated': await wrapTestComponent('sw-popover-deprecated', { sync: true }),
            'sw-highlight-text': await wrapTestComponent('sw-highlight-text'),
            'sw-media-field': await wrapTestComponent('sw-media-field'),
            'sw-media-media-item': await wrapTestComponent('sw-media-media-item'),
            'sw-media-base-item': await wrapTestComponent('sw-media-base-item'),
            'sw-media-preview-v2': await wrapTestComponent('sw-media-preview-v2'),
            'sw-colorpicker-deprecated': await wrapTestComponent('sw-text-field-deprecated'),
            'sw-upload-listener': true,
            'sw-simple-search-field': true,
            'sw-loader': true,
            'mt-datepicker': MtTextField,
            'sw-text-editor': {
                props: ['value'],
                template: '<input type="text" :value="value" @change="$emit(\'update:value\', $event.target.value)"/>',
            },
            'sw-skeleton': await wrapTestComponent('sw-skeleton'),
            'sw-skeleton-bar': await wrapTestComponent('sw-skeleton-bar'),
            'sw-switch-field-deprecated': await wrapTestComponent('sw-switch-field-deprecated'),
            'sw-button-process': true,
            'sw-media-collapse': true,
            'mt-tabs': true,
            'sw-extension-component-section': true,
            'router-link': true,
            'sw-field-copyable': true,
            'sw-ai-copilot-badge': true,
            'mt-skeleton-bar': true,
            'sw-skeleton-bar-deprecated': true,
            'mt-floating-ui': true,
            'sw-color-badge': true,
            'sw-media-upload-v2': true,
            'sw-pagination': true,
            'sw-context-menu-item': true,
            'sw-media-modal-replace': true,
            'sw-media-modal-delete': true,
            'sw-media-modal-move': true,
            'sw-media-modal-v2': true,
            'sw-context-button': true,
            'sw-product-variant-info': true,
            'sw-app-action-button': true,
            'sw-time-ago': true,
        });
    }

    return mount(
        await wrapTestComponent('sw-product-detail-specifications', {
            sync: true,
        }),
        {
            global: {
                provide: {
                    acl: {
                        can: (identifier) => {
                            if (!identifier) {
                                return true;
                            }

                            return privileges.includes(identifier);
                        },
                    },
                    repositoryFactory: {
                        create: (entity) => {
                            const overriddenRepository = repositoryFactoryCreate?.(entity);

                            if (overriddenRepository) {
                                return overriddenRepository;
                            }

                            return {
                                get: jest.fn(() => Promise.resolve({})),
                                search: jest.fn(() => Promise.resolve([])),
                            };
                        },
                    },
                    validationService: {},
                    mediaService: {},
                    systemConfigApiService: {
                        getValues: () => Promise.resolve({}),
                    },
                },
                stubs,
            },
        },
    );
}

describe('src/module/sw-product/view/sw-product-detail-specifications', () => {
    beforeEach(async () => {
        const store = Shopware.Store.get('swProductDetail');
        store.$reset();
        store.product = {
            isNew: () => false,
        };
        store.modeSettings = [
            'measurement',
            'selling_packaging',
            'properties',
            'essential_characteristics',
            'custom_fields',
        ];
        if (!Shopware.Feature.isActive('v6.8.0.0')) {
            store.creationStates = 'is-physical';
        }
        store.creationType = 'physical';
        store.advancedModeSetting = {
            value: {
                settings: [
                    {
                        key: 'measurement',
                        label: 'sw-product.specifications.cardTitleMeasurement',
                        enabled: true,
                        name: 'specifications',
                    },
                    {
                        key: 'selling_packaging',
                        label: 'sw-product.specifications.cardTitleSellingPackaging',
                        enabled: true,
                        name: 'specifications',
                    },
                    {
                        key: 'properties',
                        label: 'sw-product.specifications.cardTitleProperties',
                        enabled: true,
                        name: 'specifications',
                    },
                    {
                        key: 'essential_characteristics',
                        label: 'sw-product.specifications.cardTitleEssentialCharacteristics',
                        enabled: true,
                        name: 'specifications',
                    },
                    {
                        key: 'custom_fields',
                        label: 'sw-product.specifications.cardTitleCustomFields',
                        enabled: true,
                        name: 'specifications',
                    },
                ],
                advancedMode: {
                    enabled: true,
                    label: 'sw-product.general.textAdvancedMode',
                },
            },
        };
        Shopware.Store.get('swProductDetail').customFieldSets = [];
    });

    it('should show item fields in Selling Packaging card', async () => {
        const wrapper = await createWrapper();

        // expect the some item fields in Packaging is not hidden by css display none
        packagingItemClassName.forEach((item) => {
            const inheritedField = wrapper.find('.sw-inherit-wrapper');

            if (!inheritedField.find(item).exists()) {
                return;
            }

            expect(inheritedField.attributes().style).toBeFalsy();
        });
    });

    it('should hide item fields in Selling Packaging card when advanced mode is off', async () => {
        const wrapper = await createWrapper();
        const advancedModeSetting = Shopware.Store.get('swProductDetail').advancedModeSetting;

        Shopware.Store.get('swProductDetail').advancedModeSetting = {
            value: {
                ...advancedModeSetting.value,
                advancedMode: {
                    enabled: false,
                    label: 'sw-product.general.textAdvancedMode',
                },
            },
        };

        await nextTick();

        // expect the some item fields in Selling Packaging hidden by css display none
        packagingItemClassName.forEach((item) => {
            const inheritedField = wrapper.find('.sw-inherit-wrapper');

            if (!inheritedField.find(item).exists()) {
                return;
            }

            expect(inheritedField.attributes().style).toBe('display: none;');
        });
    });

    it('should hide Measurement card when measurement mode is unchecked', async () => {
        const wrapper = await createWrapper();
        const modeSettings = Shopware.Store.get('swProductDetail').modeSettings;

        Shopware.Store.get('swProductDetail').modeSettings = [
            ...modeSettings.filter((item) => item !== 'measurement'),
        ];

        await nextTick();

        expect(wrapper.find('.sw-product-detail-specification__measurement').exists()).toBeFalsy();
    });

    it('should hide Selling Packaging card when selling_packaging mode is unchecked', async () => {
        const wrapper = await createWrapper();
        const modeSettings = Shopware.Store.get('swProductDetail').modeSettings;

        Shopware.Store.get('swProductDetail').modeSettings = [
            ...modeSettings.filter((item) => item !== 'selling_packaging'),
        ];

        await nextTick();

        expect(wrapper.find('.sw-product-detail-specification__selling-packaging').exists()).toBeFalsy();
    });

    it('should show Properties card even advanced mode is off', async () => {
        const wrapper = await createWrapper();
        const advancedModeSetting = Shopware.Store.get('swProductDetail').advancedModeSetting;

        Shopware.Store.get('swProductDetail').advancedModeSetting = {
            value: {
                ...advancedModeSetting.value,
                advancedMode: {
                    enabled: false,
                    label: 'sw-product.general.textAdvancedMode',
                },
            },
        };

        expect(wrapper.find('sw-product-properties-stub').attributes().style).toBeFalsy();
    });

    it('should hide Properties card when properties mode is unchecked', async () => {
        const wrapper = await createWrapper();
        const modeSettings = Shopware.Store.get('swProductDetail').modeSettings;

        Shopware.Store.get('swProductDetail').modeSettings = [
            ...modeSettings.filter((item) => item !== 'properties'),
        ];
        await nextTick();

        expect(wrapper.find('sw-product-properties-stub').attributes().style).toBe('display: none;');
    });

    it('should show Essential Characteristics card when advanced mode is on', async () => {
        const wrapper = await createWrapper();
        const advancedModeSetting = Shopware.Store.get('swProductDetail').advancedModeSetting;

        Shopware.Store.get('swProductDetail').advancedModeSetting = {
            value: {
                ...advancedModeSetting.value,
                advancedMode: {
                    enabled: true,
                    label: 'sw-product.general.textAdvancedMode',
                },
            },
        };

        expect(wrapper.find('.sw-product-detail-specification__essential-characteristics').attributes().style).toBeFalsy();
    });

    it('should hide Essential Characteristics card when advanced mode is off', async () => {
        const wrapper = await createWrapper();
        const advancedModeSetting = Shopware.Store.get('swProductDetail').advancedModeSetting;

        Shopware.Store.get('swProductDetail').advancedModeSetting = {
            value: {
                ...advancedModeSetting.value,
                advancedMode: {
                    enabled: false,
                    label: 'sw-product.general.textAdvancedMode',
                },
            },
        };
        await nextTick();

        expect(wrapper.find('.sw-product-detail-specification__essential-characteristics').attributes().style).toBe(
            'display: none;',
        );
    });

    it('should hide Essential Characteristics card when essential_characteristics mode is unchecked', async () => {
        const wrapper = await createWrapper();
        const modeSettings = Shopware.Store.get('swProductDetail').modeSettings;
        const advancedModeSetting = Shopware.Store.get('swProductDetail').advancedModeSetting;

        Shopware.Store.get('swProductDetail').advancedModeSetting = {
            value: {
                ...advancedModeSetting.value,
                advancedMode: {
                    enabled: true,
                    label: 'sw-product.general.textAdvancedMode',
                },
            },
        };

        Shopware.Store.get('swProductDetail').modeSettings = [
            ...modeSettings.filter((item) => item !== 'properties'),
        ];
        await nextTick();

        expect(wrapper.find('sw-product-properties-stub').attributes().style).toBe('display: none;');
    });

    it('should show Custom Fields card advanced mode is on and custom fields set length is greater than 0', async () => {
        const wrapper = await createWrapper();

        Shopware.Store.get('swProductDetail').customFieldSets = [
            {
                customFields: [
                    1,
                    2,
                ],
            },
        ];

        const advancedModeSetting = Shopware.Store.get('swProductDetail').advancedModeSetting;
        Shopware.Store.get('swProductDetail').advancedModeSetting = {
            value: {
                ...advancedModeSetting.value,
                advancedMode: {
                    enabled: true,
                    label: 'sw-product.general.textAdvancedMode',
                },
            },
        };
        await nextTick();

        expect(wrapper.find('.sw-product-detail-specification__custom-fields').attributes().style).toBeFalsy();
    });

    it('should hide Custom Fields card when advanced mode is off', async () => {
        const wrapper = await createWrapper();
        const advancedModeSetting = Shopware.Store.get('swProductDetail').advancedModeSetting;

        Shopware.Store.get('swProductDetail').advancedModeSetting = {
            value: {
                ...advancedModeSetting.value,
                advancedMode: {
                    enabled: false,
                    label: 'sw-product.general.textAdvancedMode',
                },
            },
        };

        expect(wrapper.find('.sw-product-detail-specification__custom-fields').attributes().style).toBe('display: none;');
    });

    it('should hide Custom Fields card when custom_fields mode is unchecked', async () => {
        const wrapper = await createWrapper();
        const modeSettings = Shopware.Store.get('swProductDetail').modeSettings;
        const advancedModeSetting = Shopware.Store.get('swProductDetail').advancedModeSetting;

        Shopware.Store.get('swProductDetail').advancedModeSetting = {
            value: {
                ...advancedModeSetting.value,
                advancedMode: {
                    enabled: true,
                    label: 'sw-product.general.textAdvancedMode',
                },
            },
        };

        Shopware.Store.get('swProductDetail').modeSettings = [
            ...modeSettings.filter((item) => item !== 'custom_fields'),
        ];

        expect(wrapper.find('.sw-product-detail-specification__custom-fields').attributes().style).toBe('display: none;');
    });

    it('should not show Custom Fields card when custom fields length is smaller than 1', async () => {
        const wrapper = await createWrapper();

        await wrapper.vm.$nextTick();

        const customFieldsLength = wrapper.vm.customFieldSets.length;
        expect(customFieldsLength).toBe(0);

        const cardElement = wrapper.find('.sw-product-detail-specification__custom-fields');
        const cardStyles = cardElement.attributes('style');

        expect(cardStyles).toBe('display: none;');
    });

    it('should show Selling Packaging card when product states not includes is-download', async () => {
        const wrapper = await createWrapper();

        Shopware.Store.get('swProductDetail').product = {
            isNew: () => false,
            states: [
                'is-physical',
            ],
        };

        await wrapper.vm.$nextTick();

        const cardElement = wrapper.find('.sw-product-detail-specification__selling-packaging');
        const cardStyles = cardElement.attributes('style');
        await nextTick();

        expect(cardStyles).not.toBe('display: none;');
    });

    it('should not show Selling Packaging card when product states includes is-download', async () => {
        const wrapper = await createWrapper();

        Shopware.Store.get('swProductDetail').product = {
            isNew: () => false,
            states: [
                'is-download',
            ],
        };

        await wrapper.vm.$nextTick();

        const cardElement = wrapper.find('.sw-product-detail-specification__selling-packaging');

        expect(cardElement.exists()).toBeFalsy();
    });

    it('should not show Measurement card when product states includes is-download', async () => {
        const wrapper = await createWrapper();

        Shopware.Store.get('swProductDetail').product = {
            isNew: () => false,
            states: [
                'is-download',
            ],
        };

        await wrapper.vm.$nextTick();

        const cardElement = wrapper.find('.sw-product-detail-specification__measurement');

        expect(cardElement.exists()).toBeFalsy();
    });

    it('should hide custom field inheritance controls for a root language on the specifications page', async () => {
        const productRepositoryGet = jest.fn();

        await withTranslatedLanguageContext(
            {
                languageId: 'en-GB',
                systemLanguageId: 'en-GB',
                parentId: null,
            },
            async () => {
                setupCustomFieldSpecificationState({
                    product: createProductEntity({
                        customFields: {
                            custom_test_text: 'English value',
                            custom_test_checkbox: true,
                        },
                        translatedCustomFields: {
                            custom_test_text: 'English value',
                            custom_test_checkbox: true,
                        },
                    }),
                    parentProduct: {},
                    customFieldSets: createCustomFieldSets([
                        createTranslatedTextField(),
                        createTranslatedCheckboxField(),
                    ]),
                });

                const wrapper = await createWrapper([], {
                    renderRealCustomFieldRenderer: true,
                    ...createProductRepositoryOptions(productRepositoryGet),
                });

                await flushPromises();

                expect(wrapper.find('.sw-form-field-renderer-field__custom_test_text .mt-inheritance-switch').exists()).toBe(
                    false,
                );
                expect(
                    wrapper.find('.sw-form-field-renderer-field__custom_test_checkbox .mt-inheritance-switch').exists(),
                ).toBe(false);
                expect(productRepositoryGet).not.toHaveBeenCalled();
            },
        );
    });

    it('should show inherited custom field controls for a non-system root language on the specifications page', async () => {
        const productRepositoryGet = jest.fn(() =>
            Promise.resolve({
                customFields: {
                    custom_test_text: 'English value',
                    custom_test_checkbox: true,
                },
            }),
        );

        await withTranslatedLanguageContext(
            {
                languageId: 'de-DE',
                systemLanguageId: 'en-GB',
                parentId: null,
            },
            async () => {
                setupCustomFieldSpecificationState({
                    product: createProductEntity({
                        customFields: {
                            custom_test_text: null,
                            custom_test_checkbox: null,
                        },
                        translatedCustomFields: {},
                    }),
                    parentProduct: {},
                    customFieldSets: createCustomFieldSets([
                        createTranslatedTextField(),
                        createTranslatedCheckboxField(),
                    ]),
                });

                const wrapper = await createWrapper([], {
                    renderRealCustomFieldRenderer: true,
                    ...createProductRepositoryOptions(productRepositoryGet),
                });

                await flushPromises();

                const textInheritanceSwitch = wrapper.find(
                    '.sw-form-field-renderer-field__custom_test_text .mt-inheritance-switch',
                );
                const checkboxInheritanceSwitch = wrapper.find(
                    '.sw-form-field-renderer-field__custom_test_checkbox .mt-inheritance-switch',
                );
                const textField = wrapper.find('.sw-form-field-renderer-field__custom_test_text input[type="text"]');
                const checkboxField = wrapper.find(
                    '.sw-form-field-renderer-field__custom_test_checkbox input[type="checkbox"]',
                );

                expect(textInheritanceSwitch.exists()).toBe(true);
                expect(checkboxInheritanceSwitch.exists()).toBe(true);
                expect(textInheritanceSwitch.attributes('aria-label')).toBe('Unlink inheritance');
                expect(checkboxInheritanceSwitch.attributes('aria-label')).toBe('Unlink inheritance');
                expect(textField.element.value).toBe('English value');
                expect(textField.attributes('disabled')).toBeDefined();
                expect(checkboxField.element.checked).toBe(true);
                expect(checkboxField.attributes('disabled')).toBeDefined();
                expect(productRepositoryGet).toHaveBeenCalledTimes(1);
            },
        );
    });
});
