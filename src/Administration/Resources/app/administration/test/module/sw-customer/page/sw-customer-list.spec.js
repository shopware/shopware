import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-customer/page/sw-customer-list';

function createWrapper(privileges = []) {
    return shallowMount(Shopware.Component.build('sw-customer-list'), {
        mocks: {
            $route: {
                query: {
                    page: 1,
                    limit: 25
                }
            }
        },
        provide: {
            repositoryFactory: {
                create: (entity) => ({
                    create: () => {
                        return Promise.resolve(entity === 'customer' ? [{
                            id: '1a2b3c',
                            entity: 'customer',
                            customerId: 'd4c3b2a1',
                            productId: 'd4c3b2a1',
                            salesChannelId: 'd4c3b2a1'
                        }] : []);
                    },
                    search: () => {
                        return Promise.resolve(entity === 'customer' ? [{
                            id: '1a2b3c',
                            entity: 'customer',
                            customerId: 'd4c3b2a1',
                            productId: 'd4c3b2a1',
                            salesChannelId: 'd4c3b2a1',
                            sourceEntitiy: 'customer'
                        }] : []);
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
            filterFactory: {}
        },
        stubs: {
            'sw-page': {
                template: `
                    <div class="sw-page">
                        <slot name="smart-bar-actions"></slot>
                        <slot name="content">CONTENT</slot>
                        <slot></slot>
                    </div>`
            },
            'sw-button': true,
            'sw-icon': true,
            'sw-search-bar': true,
            'sw-entity-listing': {
                props: ['items'],
                template: `
                    <div>
                        <template v-for="item in items">
                            <slot name="actions" v-bind="{ item }"></slot>
                        </template>
                    </div>`
            },
            'sw-language-switch': true,
            'sw-empty-state': true,
            'sw-context-menu-item': true
        }
    });
}

Shopware.Service().register('filterService', () => {
    return {
        mergeWithStoredFilters: (storeKey, criteria) => criteria
    };
});

describe('module/sw-customer/page/sw-customer-list', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should not be able to create a new customer', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        const createButton = wrapper.find('.sw-customer-list__button-create');

        expect(createButton.attributes().disabled).toBeTruthy();
    });

    it('should be able to create a new customer', async () => {
        const wrapper = createWrapper([
            'customer.creator'
        ]);
        await wrapper.vm.$nextTick();

        const createButton = wrapper.find('.sw-customer-list__button-create');

        expect(createButton.attributes().disabled).toBeFalsy();
    });

    it('should not be able to inline edit', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        const entityListing = wrapper.find('.sw-customer-list-grid');

        expect(entityListing.exists()).toBeTruthy();
        expect(entityListing.attributes()['allow-inline-edit']).toBeFalsy();
    });

    it('should be able to inline edit', async () => {
        const wrapper = createWrapper([
            'customer.editor'
        ]);
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        const entityListing = wrapper.find('.sw-customer-list-grid');
        expect(entityListing.exists()).toBeTruthy();
        expect(entityListing.attributes()['allow-inline-edit']).toBeTruthy();
    });

    it('should not be able to delete', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        const deleteMenuItem = wrapper.find('.sw-customer-list__delete-action');
        expect(deleteMenuItem.attributes().disabled).toBeTruthy();
    });

    it('should be able to delete', async () => {
        const wrapper = createWrapper([
            'customer.deleter'
        ]);
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        const deleteMenuItem = wrapper.find('.sw-customer-list__delete-action');
        expect(deleteMenuItem.attributes().disabled).toBeFalsy();
    });

    it('should not be able to edit', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        const editMenuItem = wrapper.find('.sw-customer-list__edit-action');
        expect(editMenuItem.attributes().disabled).toBeTruthy();
    });

    it('should be able to edit', async () => {
        const wrapper = createWrapper([
            'customer.editor'
        ]);
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        const editMenuItem = wrapper.find('.sw-customer-list__edit-action');
        expect(editMenuItem.attributes().disabled).toBeFalsy();
    });
});
