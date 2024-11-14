/**
 * @package checkout
 */

import { mount } from '@vue/test-utils';
import { searchRankingPoint } from 'src/app/service/search-ranking.service';
import Criteria from 'src/core/data/criteria.data';

async function createWrapper(privileges = []) {
    return mount(
        await wrapTestComponent('sw-settings-customer-group-list', {
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
                stubs: {
                    'sw-page': {
                        template: `
                    <div class="sw-page">
                        <slot name="smart-bar-actions"></slot>
                        <slot name="content"></slot>
                        <slot></slot>
                    </div>`,
                    },
                    'sw-card-view': {
                        template: '<div><slot></slot></div>',
                    },
                    'sw-card': {
                        template: '<div><slot name="grid"></slot></div>',
                    },
                    'sw-context-menu-item': true,
                    'sw-button': true,
                    'sw-entity-listing': {
                        props: [
                            'items',
                            'allowEdit',
                            'allowDelete',
                            'detailRoute',
                        ],
                        template: `
                    <div>
                        <template v-for="item in items">
                            <slot name="actions" v-bind="{ item }">
                                <slot name="detail-action" v-bind="{ item }">
                                    <div class="sw-entity-listing__context-menu-edit-action"
                                         v-if="detailRoute"
                                         :disabled="!allowEdit || undefined"
                                         :routerLink="{ name: detailRoute, params: { id: item.id } }"
                                    >
                                    </div>
                                </slot>

                                <slot name="delete-action" v-bind="{ item }"></slot>
                            </slot>
                        </template>
                    </div>`,
                    },
                    'sw-empty-state': true,
                    'router-link': true,
                    'sw-search-bar': true,
                    'sw-icon': true,
                    'sw-language-info': true,
                    'sw-language-switch': true,
                },
                provide: {
                    repositoryFactory: {
                        create: () => ({
                            search: () => {
                                return Promise.resolve([
                                    {
                                        id: '1',
                                        name: 'Net price customer group',
                                        displayGross: false,
                                    },
                                ]);
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
                    searchRankingService: {
                        getSearchFieldsByEntity: () => {
                            return Promise.resolve({
                                name: searchRankingPoint.HIGH_SEARCH_RANKING,
                            });
                        },
                        buildSearchQueriesForEntity: (searchFields, term, criteria) => {
                            return criteria;
                        },
                    },
                },
            },
        },
    );
}

// These two functions contain the bare minimum for the unit test to complete
function createCustomerGroupWithCustomer() {
    return {
        customers: [
            {},
        ],
        salesChannels: [],
    };
}

function createDeletableCustomerGroup() {
    return {
        customers: [],
        salesChannels: [],
    };
}

describe('src/module/sw-settings-customer-group/page/sw-settings-customer-group-list', () => {
    it('should be a vue js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should return false if customer group has a customer and/or SalesChannel assigned to it', async () => {
        const wrapper = await createWrapper();
        const customerGroup = createCustomerGroupWithCustomer();

        expect(wrapper.vm.customerGroupCanBeDeleted(customerGroup)).toBe(false);
    });

    it('should return true if customer group has no customer and no SalesChannel assigned to id', async () => {
        const wrapper = await createWrapper();
        const customerGroup = createDeletableCustomerGroup();

        expect(wrapper.vm.customerGroupCanBeDeleted(customerGroup)).toBe(true);
    });

    it('should not be able to create without create permission', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const createButton = wrapper.find('.sw-settings-customer-group-list__create');

        expect(createButton.attributes().disabled).toBeTruthy();
    });

    it('should be able to create with create permission', async () => {
        const wrapper = await createWrapper(['customer_groups.creator']);
        await wrapper.vm.$nextTick();

        const createButton = wrapper.find('.sw-settings-customer-group-list__create');

        expect(createButton.attributes().disabled).toBeFalsy();
    });

    it('should not be able to edit without edit permission', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const editMenuItem = wrapper.find('.sw-entity-listing__context-menu-edit-action');
        expect(editMenuItem.attributes().disabled).toBeTruthy();
    });

    it('should be able to edit with edit permission', async () => {
        const wrapper = await createWrapper(['customer_groups.editor']);
        await flushPromises();

        const editMenuItem = wrapper.find('.sw-entity-listing__context-menu-edit-action');
        expect(editMenuItem.attributes().disabled).toBeFalsy();
    });

    it('should not be able to inline edit', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const entityList = wrapper.find('.sw-settings-customer-group-list-grid');

        expect(entityList.exists()).toBeTruthy();
        expect(entityList.attributes()['allow-inline-edit']).toBeFalsy();
    });

    it('should be able to inline edit', async () => {
        const wrapper = await createWrapper(['customer_groups.editor']);
        await wrapper.vm.$nextTick();

        const entityList = wrapper.find('.sw-settings-customer-group-list-grid');

        expect(entityList.exists()).toBeTruthy();
        expect(entityList.attributes()['allow-inline-edit']).toBeTruthy();
    });

    it('should not be able to delete without delete permission', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const deleteMenuItem = wrapper.find('.sw-settings-customer-group-list-grid__delete-action');
        expect(deleteMenuItem.attributes().disabled).toBeTruthy();
    });

    it('should be able to delete with delete permission', async () => {
        const wrapper = await createWrapper(['customer_groups.deleter']);
        await flushPromises();

        const deleteMenuItem = wrapper.find('.sw-settings-customer-group-list-grid__delete-action');
        expect(deleteMenuItem.attributes().disabled).toBeFalsy();
    });

    it('should hide item selection if user does not have delete permission', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const entityList = wrapper.find('.sw-settings-customer-group-list-grid');

        expect(entityList.exists()).toBeTruthy();
        expect(entityList.attributes()['show-selection']).toBeFalsy();
    });

    it('should show item selection if user has delete permission', async () => {
        const wrapper = await createWrapper(['customer_groups.deleter']);
        await wrapper.vm.$nextTick();

        const entityList = wrapper.find('.sw-settings-customer-group-list-grid');

        expect(entityList.exists()).toBeTruthy();
        expect(entityList.attributes()['show-selection']).toBeTruthy();
    });

    it('should add query score to the criteria', async () => {
        const wrapper = await createWrapper();
        await wrapper.setData({
            term: 'foo',
        });
        await wrapper.vm.$nextTick();
        wrapper.vm.searchRankingService.buildSearchQueriesForEntity = jest.fn(() => {
            return new Criteria(1, 25);
        });

        wrapper.vm.searchRankingService.getSearchFieldsByEntity = jest.fn(() => {
            return { name: 500 };
        });

        await wrapper.vm.getList();

        expect(wrapper.vm.searchRankingService.buildSearchQueriesForEntity).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.searchRankingService.getSearchFieldsByEntity).toHaveBeenCalledTimes(1);

        wrapper.vm.searchRankingService.buildSearchQueriesForEntity.mockRestore();
        wrapper.vm.searchRankingService.getSearchFieldsByEntity.mockRestore();
    });

    it('should not get search ranking fields when term is null', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();
        wrapper.vm.searchRankingService.buildSearchQueriesForEntity = jest.fn(() => {
            return new Criteria(1, 25);
        });

        wrapper.vm.searchRankingService.getSearchFieldsByEntity = jest.fn(() => {
            return {};
        });

        await wrapper.vm.getList();

        expect(wrapper.vm.searchRankingService.buildSearchQueriesForEntity).toHaveBeenCalledTimes(0);
        expect(wrapper.vm.searchRankingService.getSearchFieldsByEntity).toHaveBeenCalledTimes(0);

        wrapper.vm.searchRankingService.buildSearchQueriesForEntity.mockRestore();
        wrapper.vm.searchRankingService.getSearchFieldsByEntity.mockRestore();
    });

    it('should not build query score when search ranking field is null', async () => {
        const wrapper = await createWrapper();
        await wrapper.setData({
            term: 'foo',
        });

        await wrapper.vm.$nextTick();
        wrapper.vm.searchRankingService.buildSearchQueriesForEntity = jest.fn(() => {
            return new Criteria(1, 25);
        });

        wrapper.vm.searchRankingService.getSearchFieldsByEntity = jest.fn(() => {
            return {};
        });

        await wrapper.vm.getList();

        expect(wrapper.vm.searchRankingService.buildSearchQueriesForEntity).toHaveBeenCalledTimes(0);
        expect(wrapper.vm.searchRankingService.getSearchFieldsByEntity).toHaveBeenCalledTimes(1);

        wrapper.vm.searchRankingService.buildSearchQueriesForEntity.mockRestore();
        wrapper.vm.searchRankingService.getSearchFieldsByEntity.mockRestore();
    });

    it('should show empty state when there is not item after filling search term', async () => {
        const wrapper = await createWrapper();
        await wrapper.setData({
            term: 'foo',
        });
        await wrapper.vm.$nextTick();
        wrapper.vm.searchRankingService.getSearchFieldsByEntity = jest.fn(() => {
            return {};
        });
        await wrapper.vm.getList();

        const emptyState = wrapper.find('sw-empty-state-stub');

        expect(wrapper.vm.searchRankingService.getSearchFieldsByEntity).toHaveBeenCalledTimes(1);
        expect(emptyState.exists()).toBeTruthy();
        expect(emptyState.attributes().title).toBe('sw-empty-state.messageNoResultTitle');
        expect(wrapper.find('sw-entity-listing-stub').exists()).toBeFalsy();
        expect(wrapper.vm.entitySearchable).toBe(false);

        wrapper.vm.searchRankingService.getSearchFieldsByEntity.mockRestore();
    });
});
