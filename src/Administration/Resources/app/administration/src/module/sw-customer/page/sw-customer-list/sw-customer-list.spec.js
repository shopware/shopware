import { mount } from '@vue/test-utils';
import { searchRankingPoint } from 'src/app/service/search-ranking.service';
import Criteria from 'src/core/data/criteria.data';

async function createWrapper(privileges = []) {
    return mount(await wrapTestComponent('sw-customer-list', { sync: true }), {
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
                        create: () => {
                            return Promise.resolve(entity === 'customer' ? [{
                                id: '1a2b3c',
                                entity: 'customer',
                                customerId: 'd4c3b2a1',
                                productId: 'd4c3b2a1',
                                salesChannelId: 'd4c3b2a1',
                            }] : []);
                        },
                        search: () => {
                            return Promise.resolve(entity === 'customer' ? [{
                                id: '1a2b3c',
                                entity: 'customer',
                                customerId: 'd4c3b2a1',
                                productId: 'd4c3b2a1',
                                salesChannelId: 'd4c3b2a1',
                                sourceEntitiy: 'customer',
                                createdById: '123213132',
                            }] : []);
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
                filterFactory: {},
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
            stubs: {
                'sw-page': {
                    template: `
                    <div class="sw-page">
                        <slot name="smart-bar-actions"></slot>
                        <slot name="content">CONTENT</slot>
                        <slot></slot>
                    </div>`,
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
                        </div>`,
                },
                'sw-language-switch': true,
                'sw-empty-state': true,
                'sw-context-menu-item': true,
                'router-link': true,
            },
        },
    });
}

Shopware.Service().register('filterService', () => {
    return {
        mergeWithStoredFilters: (storeKey, criteria) => criteria,
    };
});

describe('module/sw-customer/page/sw-customer-list', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should not be able to create a new customer', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const createButton = wrapper.find('.sw-customer-list__button-create');

        expect(createButton.attributes().disabled).toBeTruthy();
    });

    it('should be able to create a new customer', async () => {
        const wrapper = await createWrapper([
            'customer.creator',
        ]);
        await flushPromises();

        const createButton = wrapper.find('.sw-customer-list__button-create');

        expect(createButton.attributes().disabled).toBeFalsy();
    });

    it('should not be able to inline edit', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const entityListing = wrapper.find('.sw-customer-list-grid');

        expect(entityListing.exists()).toBeTruthy();
        expect(entityListing.attributes()['allow-inline-edit']).toBeFalsy();
    });

    it('should be able to inline edit', async () => {
        const wrapper = await createWrapper([
            'customer.editor',
        ]);
        await flushPromises();

        const entityListing = wrapper.find('.sw-customer-list-grid');
        expect(entityListing.exists()).toBeTruthy();
        expect(entityListing.attributes()['allow-inline-edit']).toBeTruthy();
    });

    it('should not be able to delete', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const deleteMenuItem = wrapper.find('.sw-customer-list__delete-action');
        expect(deleteMenuItem.attributes().disabled).toBeTruthy();
    });

    it('should be able to delete', async () => {
        const wrapper = await createWrapper([
            'customer.deleter',
        ]);
        await flushPromises();

        const deleteMenuItem = wrapper.find('.sw-customer-list__delete-action');
        expect(deleteMenuItem.attributes().disabled).toBeFalsy();
    });

    it('should not be able to edit', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const editMenuItem = wrapper.find('.sw-customer-list__edit-action');
        expect(editMenuItem.attributes().disabled).toBeTruthy();
    });

    it('should be able to edit', async () => {
        const wrapper = await createWrapper([
            'customer.editor',
        ]);
        await flushPromises();

        const editMenuItem = wrapper.find('.sw-customer-list__edit-action');
        expect(editMenuItem.attributes().disabled).toBeFalsy();
    });

    it('should add query score to the criteria', async () => {
        const wrapper = await createWrapper();
        await wrapper.setData({
            term: 'foo',
        });
        await flushPromises();
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
        await flushPromises();
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

        await flushPromises();
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
        await flushPromises();
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

    it('should show the manual customer', async () => {
        const wrapper = await createWrapper();

        const manualLabel = wrapper.find('.sw-customer-list__created-by-admin-label');
        expect(manualLabel).toBeTruthy();
    });
});
