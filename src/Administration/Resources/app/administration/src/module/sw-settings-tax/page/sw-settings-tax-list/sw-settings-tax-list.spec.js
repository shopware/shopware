import { mount } from '@vue/test-utils';

/**
 * @package customer-order
 */
async function createWrapper(privileges = [], additionalOptions = {}) {
    return mount(
        await wrapTestComponent('sw-settings-tax-list', {
            sync: true,
        }),
        {
            global: {
                mocks: {
                    $route: {
                        query: {
                            page: 1,
                            limit: 25,
                        },
                    },
                },
                provide: {
                    repositoryFactory: {
                        create: (entity) => ({
                            search: () => {
                                if (entity === 'tax_provider') {
                                    if (additionalOptions.hasOwnProperty('taxProviders')) {
                                        return Promise.resolve(additionalOptions.taxProviders);
                                    }

                                    return Promise.resolve([
                                        {
                                            translated: {
                                                name: 'TaxProvider one',
                                            },
                                        },
                                        {
                                            translated: {
                                                name: 'TaxProvider two',
                                            },
                                        },
                                    ]);
                                }

                                return Promise.resolve([
                                    {
                                        name: 'Standard rate',
                                    },
                                    {
                                        name: 'Reduced rate',
                                    },
                                ]);
                            },

                            delete: () => {
                                return Promise.resolve();
                            },
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
                    searchRankingService: {},
                    systemConfigApiService: {
                        getConfig: () =>
                            Promise.resolve({
                                'core.tax.defaultTaxRate': '',
                            }),
                        getValues: () => Promise.resolve('defaultTaxId'),
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
                    'sw-card-view': {
                        template: `
                    <div class="sw-card-view">
                        <slot></slot>
                    </div>
                `,
                    },
                    'sw-card': {
                        template: `
                    <div class="sw-card">
                        <slot name="grid"></slot>
                    </div>
                `,
                    },
                    'sw-number-field': true,
                    'sw-entity-listing': {
                        props: ['items'],
                        template: `
                    <div>
                        <template v-for="item in items">
                            <slot name="actions" v-bind="{ item }"></slot>
                            <slot name="column-taxRate" v-bind="{ item, isInlineEdit: true }"></slot>
                        </template>
                    </div>
                `,
                    },
                    'sw-language-switch': true,
                    'sw-context-menu-item': true,
                    'sw-search-bar': true,
                    'sw-icon': true,
                    'sw-button': true,
                    'sw-modal': true,
                    'router-link': true,
                    'sw-switch-field': true,
                    'sw-button-process': {
                        template: '<button @click="$emit(\'click\', $event)"><slot></slot></button>',
                    },
                    'sw-skeleton': true,
                    'sw-skeleton-bar': true,
                    'sw-settings-tax-provider-sorting-modal': true,
                    'sw-empty-state': {
                        template: '<div class="sw-empty-state"></div>',
                    },
                    'sw-checkbox-field': true,
                },
            },
        },
    );
}

describe('module/sw-settings-tax/page/sw-settings-tax-list', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should be able to create a new tax', async () => {
        const wrapper = await createWrapper([
            'tax.creator',
        ]);
        await wrapper.vm.$nextTick();

        const addButton = wrapper.find('.sw-settings-tax-list__button-create');

        expect(addButton.attributes().disabled).toBeFalsy();
    });

    it('should not be able to create a new tax', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const addButton = wrapper.find('.sw-settings-tax-list__button-create');

        expect(addButton.attributes().disabled).toBeTruthy();
    });

    it('should be able to edit a tax', async () => {
        const wrapper = await createWrapper([
            'tax.editor',
        ]);
        await wrapper.vm.$nextTick();

        const editMenuItem = wrapper.find('.sw-tax-list__edit-action');

        expect(editMenuItem.attributes().disabled).toBeFalsy();
    });

    it('should not be able to edit a tax', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const editMenuItem = wrapper.find('.sw-tax-list__edit-action');

        expect(editMenuItem.attributes().disabled).toBeTruthy();
    });

    it('should be able to delete a tax', async () => {
        const wrapper = await createWrapper([
            'tax.deleter',
        ]);
        await wrapper.vm.$nextTick();

        const deleteMenuItem = wrapper.find('.sw-tax-list__delete-action');

        expect(deleteMenuItem.attributes().disabled).toBeFalsy();
    });

    it('should not be able to delete a tax', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const deleteMenuItem = wrapper.find('.sw-tax-list__delete-action');

        expect(deleteMenuItem.attributes().disabled).toBeTruthy();
    });

    it('should be able to inline edit a tax', async () => {
        const wrapper = await createWrapper([
            'tax.editor',
        ]);
        await wrapper.vm.$nextTick();

        const entityListing = wrapper.find('.sw-settings-tax-list-grid');

        expect(entityListing.exists()).toBeTruthy();
        expect(entityListing.attributes()['allow-inline-edit']).toBeTruthy();
    });

    it('should not be able to inline edit a tax', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const entityListing = wrapper.find('.sw-settings-tax-list-grid');

        expect(entityListing.exists()).toBeTruthy();
        expect(entityListing.attributes()['allow-inline-edit']).toBeFalsy();
    });

    it('should be able to edit a tax provider', async () => {
        const wrapper = await createWrapper([
            'tax.editor',
        ]);
        await wrapper.vm.$nextTick();

        const editMenuItem = wrapper.find('.sw-tax-provider__show-detail-link');

        expect(editMenuItem.attributes().disabled).toBeFalsy();
    });

    it('should not be able to edit a tax provider', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const editMenuItem = wrapper.find('.sw-tax-provider__show-detail-link');

        expect(editMenuItem.attributes().disabled).toBeTruthy();
    });

    it('should render button change priority for tax providers', async () => {
        const wrapper = await createWrapper([
            'tax.editor',
        ]);
        await wrapper.vm.$nextTick();

        const changePriorityButton = wrapper.find('.sw-settings-tax-provider-list-button__change-priority');

        expect(wrapper.vm.showChangePriority).toBe(true);
        expect(changePriorityButton.exists()).toBeTruthy();
        expect(wrapper.vm.showSortingModal).toBe(false);

        await changePriorityButton.trigger('click');
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.showSortingModal).toBe(true);
    });

    it('should not render button change priority for tax providers', async () => {
        const optionalTaxProviders = {
            taxProviders: [
                {
                    translated: {
                        name: 'TaxProvider one',
                    },
                },
            ],
        };
        const wrapper = await createWrapper(
            [
                'tax.editor',
            ],
            optionalTaxProviders,
        );
        await wrapper.vm.$nextTick();

        const changePriorityButton = wrapper.find('.sw-settings-tax-provider-list-button__change-priority');

        expect(changePriorityButton.exists()).toBeFalsy();
    });

    it('should be able to change tax provider active status', async () => {
        const wrapper = await createWrapper([
            'tax.editor',
        ]);
        await wrapper.vm.$nextTick();

        const taxProviderActive = wrapper.find('sw-switch-field-stub[label="sw-settings-tax.list.taxProvider.labelActive"]');

        expect(taxProviderActive.attributes().disabled).toBeFalsy();
    });

    it('should not be able to change tax provider active status', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const taxProviderActive = wrapper.find('sw-switch-field-stub[label="sw-settings-tax.list.taxProvider.labelActive"]');

        expect(taxProviderActive.attributes().disabled).toBeTruthy();
    });

    it('should render an empty state tax providers', async () => {
        const optionalTaxProviders = {
            taxProviders: [],
        };
        const wrapper = await createWrapper(
            [
                'tax.editor',
            ],
            optionalTaxProviders,
        );
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.noTaxProvidersFound).toBeTruthy();
        expect(wrapper.find('.sw-empty-state').exists()).toBeTruthy();
    });

    it('should have a tax rate field with a correct "digits" property', async () => {
        const wrapper = await createWrapper([
            'tax.editor',
        ]);

        await wrapper.vm.$nextTick();

        const entityListing = wrapper.find('.sw-settings-tax-list-grid');

        const taxRateField = entityListing.find('sw-number-field-stub');

        expect(taxRateField.attributes('digits')).toBe('3');
    });
});
