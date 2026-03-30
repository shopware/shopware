/**
 * @sw-package discovery
 * @experimental stableVersion:v6.8.0 feature:AGENTIC_AI_SALES_CHANNEL
 */

import { mount } from '@vue/test-utils';

Shopware.Defaults.agenticCommerceTypeId = '5e29f9890c4d4d519a1c7f9d5c24b7c1';

import './index';

const mockExportChannels = [
    { id: 'ec-channel-1', name: 'OpenAI Feed' },
    { id: 'ec-channel-2', name: 'Google Feed' },
];

function createMockRepositoryFactory(exportChannels = mockExportChannels) {
    return {
        create: (entity) => {
            if (entity === 'sales_channel') {
                return { search: jest.fn().mockResolvedValue(exportChannels) };
            }
            return {
                search: jest.fn().mockResolvedValue({ total: 0, [Symbol.iterator]: jest.fn() }),
            };
        },
    };
}

async function createWrapper(repositoryFactory = createMockRepositoryFactory()) {
    return mount(
        await wrapTestComponent('sw-customer-list', { sync: true }),
        {
            global: {
                stubs: {
                    'sw-page': { template: '<div><slot name="content" /></div>' },
                    'sw-icon': true,
                    'sw-sidebar': true,
                    'sw-sidebar-item': true,
                    'sw-sidebar-filter-panel': true,
                    'sw-data-grid': true,
                    'sw-pagination': true,
                    'sw-empty-state': true,
                    'mt-empty-state': true,
                    'sw-search-bar': true,
                    'sw-bulk-edit-modal': true,
                    'sw-loader': true,
                },
                provide: {
                    repositoryFactory,
                    filterFactory: {
                        create: jest.fn((entity, options) =>
                            Object.keys(options).map((key) => ({ name: key, ...options[key] })),
                        ),
                    },
                    acl: { can: jest.fn(() => true) },
                    searchRankingService: {
                        getSearchFieldsByEntity: jest.fn().mockResolvedValue({}),
                        isValidTerm: jest.fn(() => false),
                        buildSearchQueriesForEntity: jest.fn((criteria) => criteria),
                    },
                    filterService: {
                        mergeWithStoredFilters: jest.fn((storeKey, filters) => Promise.resolve(filters)),
                    },
                    feature: { isActive: jest.fn(() => true) },
                },
                mocks: {
                    $tc: (key) => key,
                    $t: (key) => key,
                    $route: {
                        query: {},
                        params: {},
                        meta: {
                            $module: { icon: 'regular-customer' },
                        },
                    },
                    $router: { push: jest.fn(), replace: jest.fn() },
                    $store: {
                        state: {
                            session: { currentUser: { id: '1' } },
                        },
                    },
                },
            },
        },
    );
}

describe('sw-export-channel-tracking extension: sw-customer-list', () => {
    let wrapper;

    afterEach(() => {
        wrapper?.unmount();
    });

    describe('defaultCriteria', () => {
        it('adds salesChannelTracking.salesChannel association on top of the default criteria', async () => {
            wrapper = await createWrapper();

            const criteria = wrapper.vm.defaultCriteria;

            expect(criteria.hasAssociation('salesChannelTracking')).toBe(true);
            expect(criteria.getAssociation('salesChannelTracking').hasAssociation('salesChannel')).toBe(true);
        });
    });

    describe('listFilters', () => {
        it('export-channel-filter is inserted directly after campaign-code-filter', async () => {
            wrapper = await createWrapper();
            await flushPromises();

            const filters = wrapper.vm.listFilters;
            const campaignIndex = filters.findIndex((f) => f.name === 'campaign-code-filter');
            const exportIndex = filters.findIndex((f) => f.name === 'export-channel-filter');

            expect(campaignIndex).toBeGreaterThanOrEqual(0);
            expect(exportIndex).toBe(campaignIndex + 1);
        });

        it('export-channel-filter uses salesChannelId property and multi-select-filter type', async () => {
            wrapper = await createWrapper();
            await flushPromises();

            const exportFilter = wrapper.vm.listFilters.find((f) => f.name === 'export-channel-filter');

            expect(exportFilter.property).toBe('salesChannelTracking.salesChannelId');
            expect(exportFilter.type).toBe('multi-select-filter');
            expect(exportFilter.valueProperty).toBe('id');
            expect(exportFilter.labelProperty).toBe('name');
        });

        it('export-channel-filter has the correct label and placeholder snippet keys', async () => {
            wrapper = await createWrapper();
            await flushPromises();

            const exportFilter = wrapper.vm.listFilters.find((f) => f.name === 'export-channel-filter');

            expect(exportFilter.label).toBe(
                'sw-export-channel-tracking.extension.sw-customer-list.filters.exportFeedFilter.label',
            );
            expect(exportFilter.placeholder).toBe(
                'sw-export-channel-tracking.extension.sw-customer-list.filters.exportFeedFilter.placeholder',
            );
        });

        it('calls filterFactory.create with entity "customer"', async () => {
            wrapper = await createWrapper();
            await flushPromises();

            const filters = wrapper.vm.listFilters;
            expect(filters).toBeDefined();

            expect(wrapper.vm.filterFactory.create).toHaveBeenCalledWith(
                'customer',
                expect.objectContaining({ 'export-channel-filter': expect.any(Object) }),
            );
        });
    });

    describe('getCustomerColumns', () => {
        it('appends the Export Channel column to the default columns', async () => {
            wrapper = await createWrapper();

            const columns = wrapper.vm.getCustomerColumns();
            const exportColumn = columns.find(
                (c) => c.property === 'extensions.salesChannelTracking.salesChannel.name',
            );

            expect(exportColumn).toBeDefined();
        });

        it('Export Channel column is hidden by default', async () => {
            wrapper = await createWrapper();

            const exportColumn = wrapper.vm
                .getCustomerColumns()
                .find((c) => c.property === 'extensions.salesChannelTracking.salesChannel.name');

            expect(exportColumn.visible).toBe(false);
        });

        it('Export Channel column has the correct label snippet key', async () => {
            wrapper = await createWrapper();

            const exportColumn = wrapper.vm
                .getCustomerColumns()
                .find((c) => c.property === 'extensions.salesChannelTracking.salesChannel.name');

            expect(exportColumn.label).toBe('sw-export-channel-tracking.extension.sw-customer-list.list.columnExportFeed');
        });

        it('Export Channel column has allowResize: true', async () => {
            wrapper = await createWrapper();

            const exportColumn = wrapper.vm
                .getCustomerColumns()
                .find((c) => c.property === 'extensions.salesChannelTracking.salesChannel.name');

            expect(exportColumn.allowResize).toBe(true);
        });

        it('does not remove any of the original columns', async () => {
            wrapper = await createWrapper();

            const columns = wrapper.vm.getCustomerColumns();
            const firstNameColumn = columns.find((c) => c.property === 'firstName');

            expect(firstNameColumn).toBeDefined();
        });
    });

    describe('createdComponent', () => {
        it('pushes export-channel-filter into defaultFilters', async () => {
            wrapper = await createWrapper();
            await flushPromises();

            expect(wrapper.vm.defaultFilters).toContain('export-channel-filter');
        });

        it('loads export channel options and populates exportChannelOptions', async () => {
            wrapper = await createWrapper();
            await flushPromises();

            expect(wrapper.vm.exportChannelOptions).toEqual(mockExportChannels);
        });

        it('fetches only Agentic Commerce type sales channels', async () => {
            const repoFactory = createMockRepositoryFactory();
            const scRepo = repoFactory.create('sales_channel');

            jest.spyOn(repoFactory, 'create').mockReturnValue(scRepo);

            wrapper = await createWrapper(repoFactory);
            await flushPromises();

            const [searchCriteria] = scRepo.search.mock.calls[0];
            const typeFilter = searchCriteria.filters.find(
                (f) => f.field === 'typeId' && f.value === Shopware.Defaults.agenticCommerceTypeId,
            );

            expect(typeFilter).toBeDefined();
        });
    });
});
