import { createLocalVue, shallowMount, enableAutoDestroy } from '@vue/test-utils';
import Vuex from 'vuex';
import 'src/module/sw-settings-shipping/page/sw-settings-shipping-detail';

enableAutoDestroy(afterEach);

function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});
    localVue.use(Vuex);

    const shippingMethod = {};
    shippingMethod.getEntityName = () => 'shipping_method';
    shippingMethod.isNew = () => false;
    shippingMethod.prices = {
        add: () => {}
    };

    return shallowMount(Shopware.Component.build('sw-settings-shipping-detail'), {
        localVue,
        provide: {
            ruleConditionDataProviderService: {},
            repositoryFactory: {
                create: () => ({
                    create: () => {
                        return shippingMethod;
                    },
                    search: () => Promise.resolve([])
                })
            },
            acl: {
                can: (identifier) => {
                    if (!identifier) { return true; }

                    return privileges.includes(identifier);
                }
            },
            customFieldDataProviderService: {
                getCustomFieldSets: () => Promise.resolve([])
            }
        },
        stubs: {
            'sw-page': {
                template: '<div><slot name="content"></slot><slot name="smart-bar-actions"></slot></div>'
            },
            'sw-button': true,
            'sw-button-process': true,
            'sw-sidebar': true,
            'sw-sidebar-media-item': true,
            'sw-card-view': true,
            'sw-card': true,
            'sw-container': true,
            'sw-field': true,
            'sw-textarea-field': true,
            'sw-upload-listener': true,
            'sw-media-upload-v2': true,
            'sw-entity-single-select': true,
            'sw-entity-tag-select': true,
            'sw-select-rule-create': true,
            'sw-settings-shipping-price-matrices': true,
            'sw-settings-shipping-tax-cost': true,
            'sw-language-info': true
        }
    });
}

describe('module/sw-settings-shipping/page/sw-settings-shipping-detail', () => {
    it('should have all fields disabled', async () => {
        const wrapper = createWrapper();
        await wrapper.setData({
            isProcessLoading: false
        });

        const saveButton = wrapper.find('.sw-settings-shipping-method-detail__save-action');
        expect(saveButton.attributes().disabled).toBe('true');

        const swFields = wrapper.findAll('sw-field-stub');
        expect(swFields.length).toBeGreaterThan(0);

        swFields.wrappers.forEach(swField => {
            expect(swField.attributes().disabled).toBe('true');
        });

        const textareaField = wrapper.find('sw-textarea-field-stub');
        expect(textareaField.attributes().disabled).toBe('true');

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
        const wrapper = createWrapper([
            'shipping.editor'
        ]);
        await wrapper.setData({
            isProcessLoading: false
        });

        const saveButton = wrapper.find('.sw-settings-shipping-method-detail__save-action');
        expect(saveButton.attributes().disabled).toBeUndefined();

        const swFields = wrapper.findAll('sw-field-stub');
        expect(swFields.length).toBeGreaterThan(0);

        swFields.wrappers.forEach(swField => {
            expect(swField.attributes().disabled).toBeUndefined();
        });

        const textareaField = wrapper.find('sw-textarea-field-stub');
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
});

