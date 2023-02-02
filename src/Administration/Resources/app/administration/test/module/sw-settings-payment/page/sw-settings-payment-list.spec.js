import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-settings-payment/page/sw-settings-payment-list';
import { searchRankingPoint } from 'src/app/service/search-ranking.service';
import Criteria from 'src/core/data/criteria.data';
import flushPromises from 'flush-promises';

function createWrapper(privileges = []) {
    return shallowMount(Shopware.Component.build('sw-settings-payment-list'), {
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
                        return Promise.resolve([
                            {
                                id: '1a2b3c4e',
                                name: 'Test settings-payment',
                                sourceEntitiy: 'settings-payment'
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
            },
            searchRankingService: {
                getSearchFieldsByEntity: () => {
                    return Promise.resolve({
                        name: searchRankingPoint.HIGH_SEARCH_RANKING
                    });
                },
                buildSearchQueriesForEntity: (searchFields, term, criteria) => {
                    return criteria;
                }
            }
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
            'sw-context-menu-item': true,
            'sw-internal-link': true,
            'sw-alert': true
        }
    });
}

describe('module/sw-settings-payment/page/sw-settings-payment-list', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should not be able to create a new payment', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        const createButton = wrapper.find('.sw-settings-payment-list__button-create');

        expect(createButton.attributes().disabled).toBeTruthy();
    });

    it('should be able to create a new settings-payment', async () => {
        const wrapper = createWrapper([
            'payment.creator'
        ]);
        await wrapper.vm.$nextTick();

        const createButton = wrapper.find('.sw-settings-payment-list__button-create');

        expect(createButton.attributes().disabled).toBeFalsy();
    });

    it('should not be able to inline edit', async () => {
        const wrapper = createWrapper();
        await flushPromises();

        const entityListing = wrapper.find('.sw-settings-payment-list-grid');

        expect(entityListing.exists()).toBeTruthy();
        expect(entityListing.attributes()['allow-inline-edit']).toBeFalsy();
    });

    it('should be able to inline edit', async () => {
        const wrapper = createWrapper([
            'payment.editor'
        ]);
        await flushPromises();

        const entityListing = wrapper.find('.sw-settings-payment-list-grid');
        expect(entityListing.exists()).toBeTruthy();
        expect(entityListing.attributes()['allow-inline-edit']).toBeTruthy();
    });

    it('should not be able to delete', async () => {
        const wrapper = createWrapper();
        await flushPromises();

        const deleteMenuItem = wrapper.find('.sw-settings-payment-list__delete-action');
        expect(deleteMenuItem.attributes().disabled).toBeTruthy();
    });

    it('should be able to delete', async () => {
        const wrapper = createWrapper([
            'payment.deleter'
        ]);
        await flushPromises();

        const deleteMenuItem = wrapper.find('.sw-settings-payment-list__delete-action');
        expect(deleteMenuItem.attributes().disabled).toBeFalsy();
    });

    it('should not be able to edit', async () => {
        const wrapper = createWrapper();
        await flushPromises();

        const editMenuItem = wrapper.find('.sw-settings-payment-list__edit-action');
        expect(editMenuItem.attributes().disabled).toBeTruthy();
    });

    it('should be able to edit', async () => {
        const wrapper = createWrapper([
            'payment.editor'
        ]);
        await flushPromises();

        const editMenuItem = wrapper.find('.sw-settings-payment-list__edit-action');
        expect(editMenuItem.attributes().disabled).toBeFalsy();
    });

    it('should add query score to the criteria', async () => {
        const wrapper = createWrapper();
        await wrapper.setData({
            term: 'foo'
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
        const wrapper = createWrapper();
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

    it('should not build query score when search ranking field is null ', async () => {
        const wrapper = createWrapper();
        await wrapper.setData({
            term: 'foo'
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
        const wrapper = createWrapper();
        await wrapper.setData({
            term: 'foo'
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
        expect(wrapper.vm.entitySearchable).toEqual(false);

        wrapper.vm.searchRankingService.getSearchFieldsByEntity.mockRestore();
    });
});

