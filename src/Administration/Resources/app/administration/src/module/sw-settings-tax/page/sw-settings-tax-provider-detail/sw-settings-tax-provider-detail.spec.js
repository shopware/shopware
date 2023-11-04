import { createLocalVue, shallowMount } from '@vue/test-utils';
import swSettingsTaxProviderDetail from 'src/module/sw-settings-tax/page/sw-settings-tax-provider-detail';

/**
 * @package checkout
 */
Shopware.Component.register('sw-settings-tax-provider-detail', swSettingsTaxProviderDetail);

async function createWrapper(privileges = [], additionalOptions = {}) {
    const localVue = createLocalVue();

    return shallowMount(await Shopware.Component.build('sw-settings-tax-provider-detail'), {
        localVue,
        provide: {
            repositoryFactory: {
                create: () => ({
                    get: () => {
                        if (additionalOptions.hasOwnProperty('taxProvider')) {
                            return Promise.resolve(additionalOptions.taxProvider);
                        }

                        return Promise.resolve({
                            active: true,
                            priority: 1,
                            availabilityRuleId: null,
                            translated: {
                                name: 'Tax provider one',
                            },
                        });
                    },
                    save: () => Promise.resolve(),
                }),
            },
            acl: {
                can: (identifier) => {
                    if (!identifier) {
                        return true;
                    }

                    return privileges.includes(identifier);
                },
            },
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
                `,
            },
            'sw-button': true,
            'sw-button-process': true,
            'sw-skeleton': true,
            'sw-card': {
                template: '<div><slot></slot><slot name="grid"></slot></div>',
            },
            'sw-card-view': {
                template: `
                        <div class="sw-card-view">
                            <slot></slot>
                        </div>
                    `,
            },
            'sw-alert': true,
            'sw-container': true,
            'sw-field': true,
            'sw-select-rule-create': true,
            'sw-extension-component-section': true,
        },
        propsData: {
            taxProviderId: 'taxProviderId',
        },
    });
}

describe('module/sw-settings-tax/page/sw-settings-tax-provider-detail', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should return metaInfo', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.$options.$createTitle = () => 'Title';

        const metaInfo = wrapper.vm.$options.metaInfo();

        expect(metaInfo.title).toBe('Title');
    });

    it('should not be able to save the tax provider', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const saveButton = wrapper.find(
            '.sw-settings-tax-tax-provider-detail__save-action',
        );

        const taxProviderPriority = wrapper.find(
            'sw-field-stub[label="sw-settings-tax.taxProviderDetail.labelPriority"]',
        );
        const taxProviderActive = wrapper.find(
            'sw-field-stub[label="sw-settings-tax.taxProviderDetail.labelActive"]',
        );

        const taxProviderAvailability = wrapper.find('.sw-settings-tax-tax-provider-detail__field-availability-rule');

        expect(saveButton.attributes().disabled).toBeTruthy();
        expect(taxProviderPriority.attributes().disabled).toBeTruthy();
        expect(taxProviderActive.attributes().disabled).toBeTruthy();
        expect(taxProviderAvailability.attributes().disabled).toBeTruthy();
    });

    it('should be able to save the tax provider', async () => {
        const wrapper = await createWrapper([
            'tax.editor',
        ]);
        await wrapper.vm.$nextTick();

        const saveButton = wrapper.find(
            '.sw-settings-tax-tax-provider-detail__save-action',
        );

        const taxProviderPriority = wrapper.find(
            'sw-field-stub[label="sw-settings-tax.taxProviderDetail.labelPriority"]',
        );
        const taxProviderActive = wrapper.find(
            'sw-field-stub[label="sw-settings-tax.taxProviderDetail.labelActive"]',
        );

        const taxProviderAvailability = wrapper.find('.sw-settings-tax-tax-provider-detail__field-availability-rule');

        expect(saveButton.attributes().disabled).toBeFalsy();
        expect(taxProviderPriority.attributes().disabled).toBeTruthy();
        expect(taxProviderActive.attributes().disabled).toBeUndefined();
        expect(taxProviderAvailability.attributes().disabled).toBeUndefined();
    });

    it('should not render sw-extension-component-section when tax provider has no identifier', async () => {
        const wrapper = await createWrapper([
            'tax.editor',
        ]);
        await wrapper.vm.$nextTick();

        const extensionComponent = wrapper.find('sw-extension-component-section-stub');

        expect(wrapper.vm.hasIdentifier).toBeFalsy();
        expect(extensionComponent.exists()).toBeFalsy();
    });

    it('should render sw-extension-component-section when tax provider has identifier', async () => {
        const optionalTaxProvider = {
            taxProvider:
                {
                    active: true,
                    priority: 1,
                    availabilityRuleId: null,
                    identifier: 'my-custom-identifier',
                    translated: {
                        name: 'Tax provider one',
                    },
                },
        };
        const wrapper = await createWrapper([
            'tax.editor',
        ], optionalTaxProvider);
        await wrapper.vm.$nextTick();

        const extensionComponent = wrapper.find('sw-extension-component-section-stub');

        expect(wrapper.vm.hasIdentifier).toBeTruthy();
        expect(extensionComponent.exists()).toBeTruthy();
        expect(extensionComponent.attributes()['position-identifier'])
            .toBe('sw-settings-tax-tax-provider-detail-custom-my-custom-identifier');
    });


    it('should handle onSave and call loadTaxProvider', async () => {
        const wrapper = await createWrapper([
            'tax.editor',
        ]);
        await wrapper.vm.$nextTick();

        const loadTaxProviderSpy = jest.spyOn(wrapper.vm, 'loadTaxProvider');

        wrapper.vm.onSave();
        await wrapper.vm.$nextTick();

        expect(loadTaxProviderSpy).toHaveBeenLastCalledWith();
    });

    it('should handle onCancel and change route', async () => {
        const wrapper = await createWrapper([
            'tax.editor',
        ]);

        wrapper.vm.onCancel();

        expect(wrapper.vm.$router.push).toHaveBeenLastCalledWith({ name: 'sw.settings.tax.index' });
    });

    it('should handle onSaveRule and set availabilityRuleId', async () => {
        const wrapper = await createWrapper([
            'tax.editor',
        ]);
        await wrapper.vm.$nextTick();

        const ruleId = 'availabilityRuleId';
        wrapper.vm.onSaveRule(ruleId);

        expect(wrapper.vm.taxProvider.availabilityRuleId).toEqual(ruleId);
    });

    it('should handle onDismissRule and set availabilityRuleId to null', async () => {
        const wrapper = await createWrapper([
            'tax.editor',
        ]);
        await wrapper.vm.$nextTick();

        wrapper.vm.taxProvider.availabilityRuleId = 'availabilityRuleId';

        wrapper.vm.onDismissRule();

        expect(wrapper.vm.taxProvider.availabilityRuleId).toBeFalsy();
    });
});
