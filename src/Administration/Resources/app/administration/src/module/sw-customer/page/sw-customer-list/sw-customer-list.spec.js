/**
 * @sw-package checkout
 */

import { mount } from '@vue/test-utils';
import { createMemoryHistory, createRouter, RouterLink } from 'vue-router';
import { searchRankingPoint } from 'src/app/service/search-ranking.service';
import Criteria from 'src/core/data/criteria.data';

async function createWrapper(privileges = [], router = null) {
    return mount(await wrapTestComponent('sw-customer-list', { sync: true }), {
        global: {
            plugins: router ? [router] : [],
            mocks: {
                $route: {
                    query: {
                        page: 1,
                        limit: 25,
                    },
                    meta: {
                        $module: {
                            icon: 'regular-content',
                        },
                    },
                },
            },
            provide: {
                repositoryFactory: {
                    create: (entity) => ({
                        create: () => {
                            return Promise.resolve(
                                entity === 'customer'
                                    ? {
                                          data: [
                                              {
                                                  id: '1a2b3c',
                                                  entity: 'customer',
                                                  customerId: 'd4c3b2a1',
                                                  productId: 'd4c3b2a1',
                                                  salesChannelId: 'd4c3b2a1',
                                              },
                                          ],
                                          total: 1,
                                      }
                                    : [],
                            );
                        },
                        search: () => {
                            return Promise.resolve(
                                entity === 'customer'
                                    ? {
                                          data: [
                                              {
                                                  id: '1a2b3c',
                                                  entity: 'customer',
                                                  customerId: 'd4c3b2a1',
                                                  productId: 'd4c3b2a1',
                                                  salesChannelId: 'd4c3b2a1',
                                                  sourceEntitiy: 'customer',
                                                  createdById: '123213132',
                                                  firstName: 'John',
                                                  lastName: 'Doe',
                                              },
                                          ],
                                          total: 1,
                                      }
                                    : [],
                            );
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
                    isValidTerm: (term) => {
                        return term && term.trim().length >= 1;
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
                'sw-search-bar': true,
                'sw-entity-listing': {
                    props: [
                        'items',
                        'dataSource',
                    ],
                    template: `
                        <div>
                            <template v-for="item in (dataSource?.data || dataSource || items)">
                                <slot name="column-firstName" v-bind="{ item, isInlineEdit: false }"></slot>
                                <slot name="actions" v-bind="{ item }"></slot>
                            </template>
                        </div>`,
                },
                'sw-language-switch': true,
                'sw-context-menu-item': true,
                'router-link': router ? RouterLink : true,
                'sw-avatar': true,
                'sw-text-field': true,
                'sw-label': true,
                'sw-checkbox-field': true,
                'sw-pagination': true,
                'sw-bulk-edit-modal': true,
                'sw-sidebar-item': true,
                'sw-sidebar-filter-panel': true,
                'sw-sidebar': true,
                'sw-time-ago': true,
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
    describe('customer name link', () => {
        let wrapper;
        let router;

        beforeEach(async () => {
            router = createRouter({
                history: createMemoryHistory('/admin#'),
                routes: [
                    { path: '/', component: { template: '<div />' } },
                    { path: '/sw/customer/index', name: 'sw.customer.index', component: { template: '<div />' } },
                    { path: '/sw/customer/detail/:id', name: 'sw.customer.detail', component: { template: '<div />' } },
                ],
            });
            await router.push({ name: 'sw.customer.index' });
            wrapper = await createWrapper([], router);
            await flushPromises();
        });

        afterEach(() => {
            wrapper?.unmount();
        });

        it('renders a customer detail URL that can be copied or opened in a new tab', () => {
            const link = wrapper.get('a.mt-link');

            expect(link.text()).toBe('Doe, John');
            expect(link.attributes('href')).toBe('#/sw/customer/detail/1a2b3c?edit=false');
        });

        it('navigates to the customer detail on a normal click', async () => {
            await wrapper.get('a.mt-link').trigger('click');
            await flushPromises();

            expect(router.currentRoute.value.fullPath).toBe('/sw/customer/detail/1a2b3c?edit=false');
        });

        it.each([
            [
                'Ctrl-click',
                'click',
                { ctrlKey: true },
            ],
            [
                'Meta-click',
                'click',
                { metaKey: true },
            ],
            [
                'middle-click',
                'auxclick',
                { button: 1 },
            ],
        ])('preserves native browser handling for %s', async (_name, eventType, options) => {
            const link = wrapper.get('a.mt-link').element;
            let navigationPrevented;
            link.addEventListener(eventType, (event) => {
                navigationPrevented = event.defaultPrevented;
                // JSDOM cannot perform native navigation after the component has handled the event.
                event.preventDefault();
            });

            link.dispatchEvent(new MouseEvent(eventType, { bubbles: true, cancelable: true, ...options }));
            await flushPromises();

            expect(navigationPrevented).toBe(false);
            expect(router.currentRoute.value.name).toBe('sw.customer.index');
        });
    });

    it('should not be able to create a new customer', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const createButton = wrapper.find('.sw-customer-list__button-create');

        expect(createButton.attributes('disabled')).toBeDefined();
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

        expect(wrapper.vm.searchRankingService.getSearchFieldsByEntity).toHaveBeenCalledTimes(1);
        expect(wrapper.find('.mt-empty-state')).toBeTruthy();
        expect(wrapper.find('.mt-empty-state__headline').text()).toBe('sw-empty-state.messageNoResultTitle');
        expect(wrapper.find('sw-entity-listing-stub').exists()).toBeFalsy();
        expect(wrapper.vm.entitySearchable).toBe(false);

        wrapper.vm.searchRankingService.getSearchFieldsByEntity.mockRestore();
    });

    it('should show the manual customer', async () => {
        const wrapper = await createWrapper();

        const manualLabel = wrapper.find('.sw-customer-list__created-by-admin-label');
        expect(manualLabel).toBeTruthy();
    });

    it('should consider criteria filters via updateCriteria', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const filter = Criteria.equals('foo', 'bar');
        wrapper.vm.updateCriteria([filter]);
        await flushPromises();

        expect(wrapper.vm.filterCriteria).toContainEqual(filter);
    });

    it('should sort the customer group column by its name field', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const customerGroupColumn = wrapper.vm.customerColumns.find((column) => column.property === 'group');

        expect(customerGroupColumn.dataIndex).toBe('group.name');
    });
});
