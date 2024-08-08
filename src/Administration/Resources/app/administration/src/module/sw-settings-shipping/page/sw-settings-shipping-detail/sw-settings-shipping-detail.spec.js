import { mount } from '@vue/test-utils';

/**
 * @package checkout
 * @group disabledCompat
 */

async function createWrapper(privileges = [], props = {}) {
    const shippingMethod = {};
    shippingMethod.technicalName = 'shipping_standard';
    shippingMethod.getEntityName = () => 'shipping_method';
    shippingMethod.isNew = () => false;
    shippingMethod.prices = {
        add: () => {},
    };

    return mount(await wrapTestComponent('sw-settings-shipping-detail', {
        sync: true,
    }), {
        props,
        global: {
            renderStubDefaultSlot: true,
            provide: {
                ruleConditionDataProviderService: {},
                repositoryFactory: {
                    create: () => ({
                        create: () => {
                            return shippingMethod;
                        },
                        search: () => Promise.resolve([]),
                        get: () => Promise.resolve(shippingMethod),
                        save: () => Promise.resolve(),
                    }),
                },
                acl: {
                    can: (identifier) => {
                        if (!identifier) { return true; }

                        return privileges.includes(identifier);
                    },
                },
                customFieldDataProviderService: {
                    getCustomFieldSets: () => Promise.resolve([]),
                },
                feature: {
                    isActive: () => true,
                },
            },
            stubs: {
                'sw-page': {
                    template: '<div><slot name="content"></slot><slot name="smart-bar-actions"></slot></div>',
                },
                'sw-button': true,
                'sw-button-process': true,
                'sw-sidebar': true,
                'sw-sidebar-media-item': true,
                'sw-card-view': true,
                'sw-card': true,
                'sw-container': true,
                'sw-text-field': {
                    props: ['disabled'],
                    template: '<input class="sw-field" :disabled="disabled" />',
                },
                'sw-number-field': {
                    props: ['disabled'],
                    template: '<input class="sw-field" :disabled="disabled" />',
                },
                'sw-switch-field': {
                    props: ['disabled'],
                    template: '<input class="sw-field" :disabled="disabled" />',
                },
                'sw-textarea-field': {
                    props: ['disabled'],
                    template: '<input class="sw-field sw-textarea-field" :disabled="disabled" />',
                },
                'sw-upload-listener': true,
                'sw-media-upload-v2': true,
                'sw-entity-single-select': true,
                'sw-entity-tag-select': true,
                'sw-select-rule-create': true,
                'sw-settings-shipping-price-matrices': true,
                'sw-settings-shipping-tax-cost': true,
                'sw-language-info': true,
                'sw-skeleton': true,
                'sw-language-switch': true,
                'sw-custom-field-set-renderer': true,
                'sw-context-menu-item': true,
            },
        },
    });
}

describe('module/sw-settings-shipping/page/sw-settings-shipping-detail', () => {
    it('should have all fields disabled', async () => {
        const wrapper = await createWrapper();
        await wrapper.setData({
            isProcessLoading: false,
        });

        await flushPromises();

        const saveButton = wrapper.find('.sw-settings-shipping-method-detail__save-action');
        expect(saveButton.attributes().disabled).toBe('true');

        const swFields = wrapper.findAll('.sw-field');
        expect(swFields.length).toBeGreaterThan(0);

        swFields.forEach(swField => {
            expect(swField.attributes().disabled).toBeDefined();
        });

        const textareaField = wrapper.find('.sw-field.sw-textarea-field');
        expect(textareaField.attributes().disabled).toBeDefined();

        const mediaUpload = wrapper.find('sw-media-upload-v2-stub');
        expect(mediaUpload.attributes().disabled).toBe('true');

        const entitySingleSelect = wrapper.find('sw-entity-single-select-stub');
        expect(entitySingleSelect.attributes().disabled).toBe('true');

        const entityTagSelect = wrapper.find('sw-entity-tag-select-stub');
        expect(entityTagSelect.attributes().disabled).toBe('true');

        const settingsShippingPriceMatrices = wrapper.find('sw-settings-shipping-price-matrices-stub');
        expect(settingsShippingPriceMatrices.attributes().disabled).toBe('true');

        const settingsShippingTax = wrapper.find('sw-settings-shipping-tax-cost-stub');
        expect(settingsShippingTax.attributes().disabled).toBe('true');
    });

    it('should have all fields enabled', async () => {
        const wrapper = await createWrapper([
            'shipping.editor',
        ]);
        await wrapper.setData({
            isProcessLoading: false,
        });

        await flushPromises();

        const saveButton = wrapper.find('.sw-settings-shipping-method-detail__save-action');
        expect(saveButton.attributes().disabled).toBeUndefined();

        const swFields = wrapper.findAll('.sw-field');
        expect(swFields.length).toBeGreaterThan(0);

        swFields.forEach(swField => {
            expect(swField.attributes().disabled).toBeUndefined();
        });

        const textareaField = wrapper.find('.sw-field.sw-textarea-field');
        expect(textareaField.attributes().disabled).toBeUndefined();

        const mediaUpload = wrapper.find('sw-media-upload-v2-stub');
        expect(mediaUpload.attributes().disabled).toBeUndefined();

        const entitySingleSelect = wrapper.find('sw-entity-single-select-stub');
        expect(entitySingleSelect.attributes().disabled).toBeUndefined();

        const entityTagSelect = wrapper.find('sw-entity-tag-select-stub');
        expect(entityTagSelect.attributes().disabled).toBeUndefined();

        const settingsShippingPriceMatrices = wrapper.find('sw-settings-shipping-price-matrices-stub');
        expect(settingsShippingPriceMatrices.attributes().disabled).toBeUndefined();

        const settingsShippingTax = wrapper.find('sw-settings-shipping-tax-cost-stub');
        expect(settingsShippingTax.attributes().disabled).toBeUndefined();
    });

    it('should add conditions association', async () => {
        const wrapper = await createWrapper();
        const criteria = wrapper.vm.ruleFilter;

        expect(criteria.associations[0].association).toBe('conditions');
    });

    it('should load customFieldSet on loadEntityData', async () => {
        const wrapper = await createWrapper([], { shippingMethodId: 'a1b2c3' });
        const spyGetMethod = jest.spyOn(wrapper.vm.shippingMethodRepository, 'get');
        const spyLoadCustomFieldSets = jest.spyOn(wrapper.vm, 'loadCustomFieldSets');

        wrapper.vm.loadEntityData();

        await flushPromises();
        expect(spyGetMethod).toHaveBeenCalled();
        expect(spyLoadCustomFieldSets).toHaveBeenCalled();
    });

    it('should create notification on save error', async () => {
        const wrapper = await createWrapper();
        const spy = jest.spyOn(wrapper.vm, 'createNotificationError');
        const warningSpy = jest.spyOn(console, 'warn').mockImplementation();
        const error = new Error('error');

        wrapper.vm.shippingMethodRepository.save = () => Promise.reject(error);
        wrapper.vm.shippingMethod.prices = [];

        await expect(wrapper.vm.onSave()).rejects.toBe(error);
        expect(spy).toHaveBeenCalled();
        expect(warningSpy).toHaveBeenCalled();
        expect(wrapper.vm.isProcessLoading).toBe(false);
    });

    it('should not load without entity id', async () => {
        const wrapper = await createWrapper();
        const spy = jest.spyOn(wrapper.vm.shippingMethodRepository, 'get');

        await flushPromises();
        wrapper.vm.loadEntityData();
        expect(spy).not.toHaveBeenCalled();
    });

    it('should load with entity id', async () => {
        const wrapper = await createWrapper([], { shippingMethodId: 'a1b2c3' });
        const spy = jest.spyOn(wrapper.vm.shippingMethodRepository, 'get');

        await flushPromises();
        wrapper.vm.loadEntityData();
        expect(spy).toHaveBeenCalled();
    });
});
