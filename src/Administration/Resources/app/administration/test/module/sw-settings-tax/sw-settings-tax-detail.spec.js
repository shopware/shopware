import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-settings-tax/page/sw-settings-tax-detail';

function createWrapper(privileges = [], isShopwareDefaultTax = true) {
    return shallowMount(Shopware.Component.build('sw-settings-tax-detail'), {
        mocks: {
            $te: () => isShopwareDefaultTax
        },
        provide: {
            repositoryFactory: {
                create: () => ({
                    get: () => {
                        return Promise.resolve({
                            isNew: () => false
                        });
                    },

                    create: () => {
                        return Promise.resolve({
                            isNew: () => true
                        });
                    },

                    save: () => {
                        return Promise.resolve();
                    }
                })
            },
            acl: {
                can: (identifier) => {
                    if (!identifier) {
                        return true;
                    }

                    return privileges.includes(identifier);
                }
            },
            customFieldDataProviderService: {
                getCustomFieldSets: () => Promise.resolve([])
            }
        },
        stubs: {
            'sw-page': {
                template: `
                    <div class="sw-page">
                        <slot name="search-bar"></slot>
                        <slot name="smart-bar-back"></slot>
                        <slot name="smart-bar-header"></slot>
                        <slot name="language-switch"></slot>
                        <slot name="smart-bar-actions"></slot>
                        <slot name="side-content"></slot>
                        <slot name="content"></slot>
                        <slot name="sidebar"></slot>
                        <slot></slot>
                    </div>
                `
            },
            'sw-card-view': true,
            'sw-card': true,
            'sw-container': true,
            'sw-button': true,
            'sw-button-process': true,
            'sw-text-field': true,
            'sw-number-field': true
        }
    });
}

describe('module/sw-settings-tax/page/sw-settings-tax-detail', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should be able to save the tax', async () => {
        const wrapper = createWrapper([
            'tax.editor'
        ]);
        await wrapper.vm.$nextTick();

        const saveButton = wrapper.find(
            '.sw-settings-tax-detail__save-action'
        );
        const taxNameField = wrapper.find(
            'sw-text-field-stub[label="sw-settings-tax.detail.labelName"]'
        );
        const taxRateField = wrapper.find(
            'sw-number-field-stub[label="sw-settings-tax.detail.labelDefaultTaxRate"]'
        );

        expect(saveButton.attributes().disabled).toBeFalsy();
        expect(taxNameField.attributes().disabled).toBeTruthy();
        expect(taxRateField.attributes().disabled).toBeUndefined();
    });

    it('the name should be editable for non default rates', async () => {
        const wrapper = createWrapper([
            'tax.editor'
        ], false);
        await wrapper.vm.$nextTick();

        const taxNameField = wrapper.find(
            'sw-text-field-stub[label="sw-settings-tax.detail.labelName"]'
        );
        expect(taxNameField.attributes().disabled).toBeUndefined();
    });

    it('should not be able to save the tax', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        const saveButton = wrapper.find(
            '.sw-settings-tax-detail__save-action'
        );
        const taxNameField = wrapper.find(
            'sw-text-field-stub[label="sw-settings-tax.detail.labelName"]'
        );
        const taxRateField = wrapper.find(
            'sw-number-field-stub[label="sw-settings-tax.detail.labelDefaultTaxRate"]'
        );

        expect(saveButton.attributes().disabled).toBeTruthy();
        expect(taxNameField.attributes().disabled).toBeTruthy();
        expect(taxRateField.attributes().disabled).toBeTruthy();
    });
});
