/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';
import selectMtSelectOptionByText from 'test/_helper_/select-mt-select-by-text';

function getFieldTypes() {
    return {
        select: {
            configRenderComponent: 'sw-custom-field-type-select',
            config: {
                componentName: 'sw-single-select',
            },
        },
        checkbox: {
            configRenderComponent: 'sw-custom-field-type-checkbox',
            type: 'bool',
            config: { componentName: 'sw-field', type: 'checkbox' },
        },
        switch: {
            configRenderComponent: 'sw-custom-field-type-checkbox',
            type: 'bool',
            config: { componentName: 'sw-field', type: 'switch' },
        },
    };
}

const customFieldFixture = {
    id: 'id1',
    name: 'custom_additional_field_1',
    config: {
        label: { 'en-GB': 'Special field 1' },
        customFieldType: 'checkbox',
        customFieldPosition: 1,
    },
    _isNew: true,
    getEntityName: () => 'custom_field',
};

const defaultProps = {
    currentCustomField: customFieldFixture,
    set: {},
};

async function createWrapper(props = defaultProps, privileges = []) {
    return mount(
        await wrapTestComponent('sw-custom-field-detail', {
            sync: true,
        }),
        {
            props,
            global: {
                renderStubDefaultSlot: true,
                mocks: {
                    $i18n: {
                        fallbackLocale: 'en-GB',
                    },
                },
                provide: {
                    acl: {
                        can: (identifier) => {
                            if (!identifier) {
                                return true;
                            }

                            return privileges.includes(identifier);
                        },
                    },
                    customFieldDataProviderService: {
                        getTypes: () => getFieldTypes(),
                    },
                    SwCustomFieldListIsCustomFieldNameUnique: () => Promise.resolve(true),
                    validationService: {},
                    shortcutService: {
                        stopEventListener: () => {},
                        startEventListener: () => {},
                    },
                },
                stubs: {
                    'sw-modal': await wrapTestComponent('sw-modal'),
                    'sw-container': true,
                    'sw-custom-field-type-checkbox': true,
                    'mt-number-field': true,
                    'sw-text-field': true,
                    'sw-select-field': await wrapTestComponent('sw-select-field', { sync: true }),
                    'sw-select-field-deprecated': await wrapTestComponent('sw-select-field-deprecated', { sync: true }),
                    'sw-block-field': await wrapTestComponent('sw-block-field'),
                    'sw-base-field': await wrapTestComponent('sw-base-field'),
                    'sw-field-error': true,
                    'sw-help-text': true,
                    'sw-loader': true,
                    'router-link': true,
                    'sw-inheritance-switch': true,
                    'sw-ai-copilot-badge': true,
                    'mt-switch': await wrapTestComponent('mt-switch'),
                    'mt-banner': await wrapTestComponent('mt-banner'),
                },
            },
        },
    );
}

describe('src/module/sw-settings-custom-field/component/sw-custom-field-detail', () => {
    it('can edit fields', async () => {
        const wrapper = await createWrapper(defaultProps, ['custom_field.editor']);
        await flushPromises();

        const modalTypeField = wrapper.find('.sw-custom-field-detail__modal-type input');
        const technicalNameField = wrapper.findComponent('.sw-custom-field-detail__technical-name');
        const modalPositionField = wrapper.find('.sw-custom-field-detail__modal-position');
        const modalSaveButton = wrapper.find('.sw-custom-field-detail__footer-save');

        expect(modalTypeField.attributes('disabled')).toBeUndefined();
        expect(technicalNameField.props('disabled')).toBe(false);
        expect(modalPositionField.attributes('disabled')).toBeUndefined();
        expect(modalSaveButton.attributes('disabled')).toBeUndefined();
    });

    it('cannot edit fields', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const modalTypeField = wrapper.find('.sw-custom-field-detail__modal-type input');
        const technicalNameField = wrapper.findComponent('.sw-custom-field-detail__technical-name');
        const modalPositionField = wrapper.find('.sw-custom-field-detail__modal-position');
        const modalSaveButton = wrapper.find('.sw-custom-field-detail__footer-save');

        expect(modalTypeField.attributes('disabled')).toBeDefined();
        expect(technicalNameField.props('disabled')).toBe(true);
        expect(modalPositionField.attributes('disabled')).toBeDefined();
        expect(modalSaveButton.attributes('disabled')).toBeDefined();
    });

    it('should update config correctly', async () => {
        const wrapper = await createWrapper(defaultProps, ['custom_field.editor']);
        await flushPromises();

        await selectMtSelectOptionByText(wrapper, 'sw-settings-custom-field.types.select');

        await flushPromises();

        expect(wrapper.vm.currentCustomField.config).toEqual(
            expect.objectContaining({
                customFieldType: 'select',
            }),
        );

        await selectMtSelectOptionByText(wrapper, 'sw-settings-custom-field.types.switch');

        expect(wrapper.vm.currentCustomField.config).toEqual(
            expect.objectContaining({
                customFieldType: 'switch',
            }),
        );

        const saveButton = wrapper.find('.sw-custom-field-detail__footer-save');
        await saveButton.trigger('click');

        expect(wrapper.vm.currentCustomField.config).toEqual(
            expect.objectContaining({
                customFieldType: 'switch',
                componentName: 'sw-field',
            }),
        );
    });

    it('should show error if custom field name is invalid', async () => {
        const wrapper = await createWrapper(defaultProps, ['custom_field.editor']);
        await flushPromises();

        expect(wrapper.find('.sw-custom-field-detail__technical-name .mt-field__error').exists()).toBe(false);

        await selectMtSelectOptionByText(wrapper, 'sw-settings-custom-field.types.select');
        await flushPromises();

        await wrapper.find('.sw-custom-field-detail__technical-name input').setValue('invalid-name.');
        expect(wrapper.vm.currentCustomField.name).toBe('invalid-name.');
        await flushPromises();

        await wrapper.find('.sw-custom-field-detail__footer-save').trigger('click');
        expect(wrapper.emitted('custom-field-edit-save')).toBeDefined();

        Shopware.Store.get('error').addApiError({
            expression: `custom_field.id1.name.error`,
            error: new Shopware.Classes.ShopwareError({ code: 'test', detail: 'test' }),
        });
        await flushPromises();

        expect(wrapper.find('.sw-custom-field-detail__technical-name .mt-field__error').exists()).toBe(true);
        expect(wrapper.find('.sw-custom-field-detail__technical-name .mt-field__error').text()).toBe('test');
    });

    it('should set searchable to false by default for new custom fields', async () => {
        const wrapper = await createWrapper(defaultProps, ['custom_field.editor']);
        await flushPromises();

        expect(wrapper.vm.currentCustomField.searchable).toBe(false);
    });

    it('should preserve searchable value for existing custom fields', async () => {
        const existingField = {
            ...customFieldFixture,
            _isNew: false,
            searchable: true,
        };

        const wrapper = await createWrapper({
            currentCustomField: existingField,
            set: {},
        }, ['custom_field.editor']);
        await flushPromises();

        expect(wrapper.vm.currentCustomField.searchable).toBe(true);
        expect(wrapper.vm.originalSearchable).toBe(true);
    });

    it('should show searchable toggle', async () => {
        const wrapper = await createWrapper(defaultProps, ['custom_field.editor']);
        await flushPromises();

        const searchableToggle = wrapper.find('.sw-custom-field-detail__allow-searchable');
        expect(searchableToggle.exists()).toBe(true);
    });

    it('should show banner when enabling searchable on existing product custom field', async () => {
        const existingProductField = {
            ...customFieldFixture,
            _isNew: false,
            searchable: false,
        };

        const wrapper = await createWrapper({
            currentCustomField: existingProductField,
            set: {
                relations: [{ entityName: 'product' }],
            },
        }, ['custom_field.editor']);
        await flushPromises();

        expect(wrapper.vm.showSearchableChangeBanner).toBe(false);

        // Enable searchable
        wrapper.vm.currentCustomField.searchable = true;
        await flushPromises();

        expect(wrapper.vm.showSearchableChangeBanner).toBe(true);
    });

    it('should not show banner for new custom fields', async () => {
        const wrapper = await createWrapper({
            currentCustomField: {
                ...customFieldFixture,
                _isNew: true,
                searchable: false,
            },
            set: {
                relations: [{ entityName: 'product' }],
            },
        }, ['custom_field.editor']);
        await flushPromises();

        wrapper.vm.currentCustomField.searchable = true;
        await flushPromises();

        expect(wrapper.vm.showSearchableChangeBanner).toBe(false);
    });

    it('should not show banner for non-product custom fields', async () => {
        const existingCustomerField = {
            ...customFieldFixture,
            _isNew: false,
            searchable: false,
        };

        const wrapper = await createWrapper({
            currentCustomField: existingCustomerField,
            set: {
                relations: [{ entityName: 'customer' }],
            },
        }, ['custom_field.editor']);
        await flushPromises();

        wrapper.vm.currentCustomField.searchable = true;
        await flushPromises();

        expect(wrapper.vm.showSearchableChangeBanner).toBe(false);
    });

    it('should hide banner when disabling searchable again', async () => {
        const existingProductField = {
            ...customFieldFixture,
            _isNew: false,
            searchable: false,
        };

        const wrapper = await createWrapper({
            currentCustomField: existingProductField,
            set: {
                relations: [{ entityName: 'product' }],
            },
        }, ['custom_field.editor']);
        await flushPromises();

        // Enable searchable
        wrapper.vm.currentCustomField.searchable = true;
        await flushPromises();
        expect(wrapper.vm.showSearchableChangeBanner).toBe(true);

        // Disable searchable again
        wrapper.vm.currentCustomField.searchable = false;
        await flushPromises();
        expect(wrapper.vm.showSearchableChangeBanner).toBe(false);
    });

    it('should not show banner when searchable was already true', async () => {
        const existingProductField = {
            ...customFieldFixture,
            _isNew: false,
            searchable: true,
        };

        const wrapper = await createWrapper({
            currentCustomField: existingProductField,
            set: {
                relations: [{ entityName: 'product' }],
            },
        }, ['custom_field.editor']);
        await flushPromises();

        wrapper.vm.currentCustomField.searchable = true;
        await flushPromises();

        expect(wrapper.vm.showSearchableChangeBanner).toBe(false);
    });
});
