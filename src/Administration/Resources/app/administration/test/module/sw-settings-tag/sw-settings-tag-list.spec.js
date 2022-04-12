import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-settings-tag/page/sw-settings-tag-list';

function createWrapper(privileges = []) {
    const localVue = createLocalVue();

    const responseMock = [
        {
            id: '1',
            name: 'ExampleTag'
        },
        {
            id: '2',
            name: 'AnotherExampleTag'
        }
    ];
    const connections = {
        products: 412,
        media: 112,
        categories: 16,
        customers: 1,
        orders: 33,
        shippingMethods: 0,
        newsletterRecipients: 0,
        landingPages: 3,
        rules: 0
    };

    responseMock.aggregations = {};
    responseMock.total = 2;

    Object.keys(connections).forEach((connection) => {
        responseMock.aggregations[connection] = {
            buckets: [
                {
                    key: '1',
                    [connection]: {
                        count: connections[connection]
                    }
                }
            ]
        };
    });

    return shallowMount(Shopware.Component.build('sw-settings-tag-list'), {
        localVue,
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
                create: () => ({
                    search: () => {
                        return Promise.resolve(responseMock);
                    },

                    delete: () => {
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
            searchRankingService: {},
            tagApiService: {
                filterIds: jest.fn(() => Promise.resolve({ total: 1, ids: ['1'] }))
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
            'sw-card-view': {
                template: `
                    <div class="sw-card-view">
                        <slot></slot>
                    </div>
                `
            },
            'sw-card': {
                template: `
                    <div class="sw-card">
                        <slot name="grid"></slot>
                    </div>
                `
            },
            'sw-entity-listing': {
                props: ['items'],
                template: `
                    <div>
                        <template v-for="item in items">
                            <slot name="actions" v-bind="{ item }"></slot>
                        </template>
                    </div>
                `
            },
            'sw-context-menu-item': true,
            'sw-search-bar': true,
            'sw-icon': true,
            'sw-button': true,
            'sw-modal': true,
            'sw-empty-state': true
        }
    });
}

describe('module/sw-settings-tag/page/sw-settings-tag-list', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should be able to create a new tag', async () => {
        const wrapper = createWrapper([
            'tag.creator'
        ]);
        await wrapper.vm.$nextTick();

        const addButton = wrapper.find('.sw-settings-tag-list__button-create');

        expect(addButton.attributes().disabled).toBeFalsy();

        const duplicateMenuItem = wrapper.find('.sw-settings-tag-list__duplicate-action');

        expect(duplicateMenuItem.attributes().disabled).toBeFalsy();
    });

    it('should not be able to create a new tag', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        const addButton = wrapper.find('.sw-settings-tag-list__button-create');

        expect(addButton.attributes().disabled).toBeTruthy();

        const duplicateMenuItem = wrapper.find('.sw-settings-tag-list__duplicate-action');

        expect(duplicateMenuItem.attributes().disabled).toBeTruthy();
    });

    it('should be able to edit a tag', async () => {
        const wrapper = createWrapper([
            'tag.editor'
        ]);
        await wrapper.vm.$nextTick();

        const editMenuItem = wrapper.find('.sw-settings-tag-list__edit-action');

        expect(editMenuItem.attributes().disabled).toBeFalsy();
    });

    it('should not be able to edit a tag', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        const editMenuItem = wrapper.find('.sw-settings-tag-list__edit-action');

        expect(editMenuItem.attributes().disabled).toBeTruthy();
    });

    it('should be able to delete a tag', async () => {
        const wrapper = createWrapper([
            'tag.deleter'
        ]);
        await wrapper.vm.$nextTick();

        const deleteMenuItem = wrapper.find('.sw-settings-tag-list__delete-action');

        expect(deleteMenuItem.attributes().disabled).toBeFalsy();
    });

    it('should not be able to delete a tag', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        const deleteMenuItem = wrapper.find('.sw-settings-tag-list__delete-action');

        expect(deleteMenuItem.attributes().disabled).toBeTruthy();
    });

    it('should return summary of total connections', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        const summary = wrapper.vm.getConnections('1');

        expect(summary).toEqual([
            { property: 'products', entity: 'product', count: 412 },
            { property: 'media', entity: 'media', count: 112 },
            { property: 'categories', entity: 'category', count: 16 },
            { property: 'customers', entity: 'customer', count: 1 },
            { property: 'orders', entity: 'order', count: 33 },
            { property: 'landingPages', entity: 'landing_page', count: 3 }
        ]);
    });

    it('should use tag api service for duplicate filter', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.total).toEqual(2);

        await wrapper.setData({
            duplicateFilter: true
        });

        wrapper.vm.onFilter();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.tagApiService.filterIds).toBeCalledTimes(1);
        expect(wrapper.vm.total).toEqual(1);
    });
});
