/**
 * @sw-package after-sales
 */
import { mount } from '@vue/test-utils';

Shopware.Service().register('filterService', () => {
    return {
        mergeWithStoredFilters: (storeKey, criteria) => criteria,
    };
});

async function createWrapper({ reviews = null, stubs = {} } = {}) {
    return mount(await await wrapTestComponent('sw-review-list', { sync: true }), {
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
                    create: () => ({
                        create: () => {
                            return Promise.resolve([
                                {
                                    id: '1a2b3c',
                                    entity: 'review',
                                    customerId: 'd4c3b2a1',
                                    productId: 'd4c3b2a1',
                                    salesChannelId: 'd4c3b2a1',
                                },
                            ]);
                        },
                        search: () => {
                            if (reviews) {
                                return Promise.resolve(Object.assign([...reviews], { total: reviews.length }));
                            }

                            return Promise.resolve({
                                total: 1,
                            });
                        },
                    }),
                },
                filterFactory: {
                    create: () => [],
                },
                searchRankingService: {
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
                'sw-entity-listing': true,
                'sw-language-switch': true,
                'sw-empty-state': true,
                'sw-context-menu-item': true,
                'sw-data-grid-column-boolean': true,
                'router-link': true,
                'sw-rating-stars': true,
                'sw-sidebar-item': true,
                'sw-sidebar': true,
                'sw-sidebar-filter-panel': true,
                'sw-time-ago': true,
                ...stubs,
            },
        },
    });
}

describe('module/sw-review/page/sw-review-list', () => {
    it('should sort the name column by the company after the person name', async () => {
        const listing = {
            props: ['columns'],
            template: '<div></div>',
        };
        const wrapper = await createWrapper({ stubs: { 'sw-entity-listing': listing } });

        const columns = wrapper.findComponent(listing).props('columns');
        expect(columns.find((column) => column.property === 'user').dataIndex).toBe(
            'customer.lastName,customer.firstName,customer.company',
        );
    });

    it.each([
        [
            'a contact person',
            { firstName: 'Ada', lastName: 'Lovelace', displayName: 'Ada Lovelace' },
            'Lovelace, Ada',
        ],
        [
            'a company without a contact person',
            { firstName: '', lastName: '', displayName: 'Acme GmbH' },
            'Acme GmbH',
        ],
        [
            'a company with whitespace names',
            { firstName: ' ', lastName: '  ', displayName: 'Acme GmbH' },
            'Acme GmbH',
        ],
        [
            'a surname alone',
            { firstName: null, lastName: 'Lovelace', displayName: 'Lovelace' },
            'Lovelace',
        ],
    ])('should name %s in the list', async (_name, customer, expected) => {
        const wrapper = await createWrapper({
            reviews: [{ id: '1', customerId: '1', customer }],
            stubs: {
                'sw-entity-listing': {
                    props: ['dataSource'],
                    template: `
                        <div>
                            <div v-for="item in dataSource" class="sw-review-list-test__user">
                                <slot name="column-user" v-bind="{ item }"></slot>
                            </div>
                        </div>`,
                },
                'router-link': {
                    template: '<a><slot></slot></a>',
                },
            },
        });
        await flushPromises();

        expect(wrapper.find('.sw-review-list-test__user').text()).toBe(expected);
    });

    it('should not be able to delete', async () => {
        const wrapper = await createWrapper();
        await wrapper.setData({ total: 2 });
        await flushPromises();

        const deleteMenuItem = wrapper.find('sw-entity-listing-stub');
        expect(deleteMenuItem.attributes()['allow-delete']).toBe('false');
    });

    it('should be able to delete', async () => {
        global.activeAclRoles = ['review.deleter'];

        const wrapper = await createWrapper();
        await wrapper.setData({ total: 2 });
        await flushPromises();

        const deleteMenuItem = wrapper.find('sw-entity-listing-stub');
        expect(deleteMenuItem.attributes()['allow-delete']).toBe('true');
    });

    it('should not be able to edit', async () => {
        const wrapper = await createWrapper();
        await wrapper.setData({ total: 2 });
        await flushPromises();

        const editMenuItem = wrapper.find('sw-entity-listing-stub');
        expect(editMenuItem.attributes()['allow-edit']).toBe('false');
    });

    it('should be able to edit', async () => {
        global.activeAclRoles = ['review.editor'];

        const wrapper = await createWrapper();
        await wrapper.setData({ total: 2 });
        await flushPromises();

        const editMenuItem = wrapper.find('sw-entity-listing-stub');
        expect(editMenuItem.attributes()['allow-edit']).toBe('true');
    });

    it('should have default filters configured', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const defaultFilters = wrapper.vm.defaultFilters;
        expect(defaultFilters).toEqual([
            'sales-channel-filter',
            'status-filter',
            'language-filter',
            'customer-filter',
            'product-filter',
            'points-filter',
        ]);

        // Verify that all default filters are present in listFilterOptions
        defaultFilters.forEach((filterKey) => {
            expect(wrapper.vm.listFilterOptions).toHaveProperty(filterKey);
        });
    });
});
