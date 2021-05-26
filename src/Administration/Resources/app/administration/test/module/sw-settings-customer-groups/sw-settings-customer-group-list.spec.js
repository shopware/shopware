import { shallowMount, createLocalVue } from '@vue/test-utils';
import 'src/module/sw-settings-customer-group/page/sw-settings-customer-group-list';

function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    return shallowMount(Shopware.Component.build('sw-settings-customer-group-list'), {
        localVue,
        mocks: {
            $route: {
                query: {
                    page: 1,
                    limit: 25
                }
            }
        },
        stubs: {
            'sw-page': {
                template: `
                    <div class="sw-page">
                        <slot name="smart-bar-actions"></slot>
                        <slot name="content"></slot>
                        <slot></slot>
                    </div>`
            },
            'sw-card-view': {
                template: '<div><slot></slot></div>'
            },
            'sw-card': {
                template: '<div><slot name="grid"></slot></div>'
            },
            'sw-context-menu-item': true,
            'sw-button': true,
            'sw-entity-listing': {
                props: ['items', 'allowEdit', 'allowDelete', 'detailRoute'],
                template: `
                    <div>
                        <template v-for="item in items">
                            <slot name="actions" v-bind="{ item }">
                                <slot name="detail-action" v-bind="{ item }">
                                    <sw-context-menu-item-stub class="sw-entity-listing__context-menu-edit-action"
                                                          v-if="detailRoute"
                                                          :disabled="!allowEdit"
                                                          :routerLink="{ name: detailRoute, params: { id: item.id } }">
                                    </sw-context-menu-item-stub>
                                </slot>

                                <slot name="delete-action" v-bind="{ item }"></slot>
                            </slot>
                        </template>
                    </div>`
            }
        },
        provide: {
            repositoryFactory: {
                create: () => ({
                    search: () => {
                        return Promise.resolve([
                            {
                                id: '1',
                                name: 'Net price customer group',
                                displayGross: false
                            }
                        ]);
                    }
                })
            },
            acl: {
                can: (identifier) => {
                    if (!identifier) { return true; }

                    return privileges.includes(identifier);
                }
            }
        }
    });
}

// These two functions contain the bare minimum for the unit test to complete
function createCustomerGroupWithCustomer() {
    return {
        customers: [
            {}
        ],
        salesChannels: []
    };
}

function createDeletableCustomerGroup() {
    return {
        customers: [],
        salesChannels: []
    };
}

describe('src/module/sw-settings-customer-group/page/sw-settings-customer-group-list', () => {
    it('should be a vue js component', async () => {
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should return false if customer group has a customer and/or SalesChannel assigned to it', async () => {
        const wrapper = createWrapper();
        const customerGroup = createCustomerGroupWithCustomer();

        expect(wrapper.vm.customerGroupCanBeDeleted(customerGroup)).toBe(false);
    });

    it('should return true if customer group has no customer and no SalesChannel assigned to id', async () => {
        const wrapper = createWrapper();
        const customerGroup = createDeletableCustomerGroup();

        expect(wrapper.vm.customerGroupCanBeDeleted(customerGroup)).toBe(true);
    });

    it('should not be able to create without create permission', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        const createButton = wrapper.find('.sw-settings-customer-group-list__create');

        expect(createButton.attributes().disabled).toBeTruthy();
    });

    it('should be able to create with create permission', async () => {
        const wrapper = createWrapper(['customer_groups.creator']);
        await wrapper.vm.$nextTick();

        const createButton = wrapper.find('.sw-settings-customer-group-list__create');

        expect(createButton.attributes().disabled).toBeFalsy();
    });

    it('should not be able to edit without edit permission', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        const editMenuItem = wrapper.find('.sw-entity-listing__context-menu-edit-action');
        expect(editMenuItem.attributes().disabled).toBeTruthy();
    });

    it('should be able to edit with edit permission', async () => {
        const wrapper = createWrapper(['customer_groups.editor']);
        await wrapper.vm.$nextTick();

        const editMenuItem = wrapper.find('.sw-entity-listing__context-menu-edit-action');
        expect(editMenuItem.attributes().disabled).toBeFalsy();
    });

    it('should not be able to inline edit', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        const entityList = wrapper.find('.sw-settings-customer-group-list-grid');

        expect(entityList.exists()).toBeTruthy();
        expect(entityList.attributes()['allow-inline-edit']).toBeFalsy();
    });

    it('should be able to inline edit', async () => {
        const wrapper = createWrapper(['customer_groups.editor']);
        await wrapper.vm.$nextTick();

        const entityList = wrapper.find('.sw-settings-customer-group-list-grid');

        expect(entityList.exists()).toBeTruthy();
        expect(entityList.attributes()['allow-inline-edit']).toBeTruthy();
    });

    it('should not be able to delete without delete permission', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        const deleteMenuItem = wrapper.find('.sw-settings-customer-group-list-grid__delete-action');
        expect(deleteMenuItem.attributes().disabled).toBeTruthy();
    });

    it('should be able to delete with delete permission', async () => {
        const wrapper = createWrapper(['customer_groups.deleter']);
        await wrapper.vm.$nextTick();

        const deleteMenuItem = wrapper.find('.sw-settings-customer-group-list-grid__delete-action');
        expect(deleteMenuItem.attributes().disabled).toBeFalsy();
    });

    it('should hide item selection if user does not have delete permission', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        const entityList = wrapper.find('.sw-settings-customer-group-list-grid');

        expect(entityList.exists()).toBeTruthy();
        expect(entityList.attributes()['show-selection']).toBeFalsy();
    });

    it('should show item selection if user has delete permission', async () => {
        const wrapper = createWrapper(['customer_groups.deleter']);
        await wrapper.vm.$nextTick();

        const entityList = wrapper.find('.sw-settings-customer-group-list-grid');

        expect(entityList.exists()).toBeTruthy();
        expect(entityList.attributes()['show-selection']).toBeTruthy();
    });
});
