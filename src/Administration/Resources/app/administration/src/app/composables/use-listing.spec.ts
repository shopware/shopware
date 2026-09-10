/**
 * @sw-package framework
 */
import { flushPromises, mount } from '@vue/test-utils';
import { createRouter, createWebHashHistory } from 'vue-router';
import type { RouteLocationRaw, Router } from 'vue-router';
import useListing from './use-listing';
import type { UseListingOptions, UseListingReturn } from './use-listing';

const searchRankingService = {
    isValidTerm: jest.fn((term: string) => term.trim().length >= 1),
    getSearchFieldsByEntity: jest.fn(),
    buildSearchQueriesForEntity: jest.fn(),
};

interface MountResult {
    listing: UseListingReturn;
    getList: jest.Mock;
    router: Router;
    /** How often `getList` had run by the end of the setup function. */
    callsDuringSetup: number;
}

async function mountListing(
    options: Partial<UseListingOptions> = {},
    initialRoute: RouteLocationRaw = { name: 'sw.product.index' },
): Promise<MountResult> {
    const getList = (options.getList as jest.Mock | undefined) ?? jest.fn();
    let listing: UseListingReturn | undefined;
    let callsDuringSetup = 0;

    const router = createRouter({
        routes: [
            {
                name: 'sw.product.index',
                path: '/sw/product/index',
                component: {
                    template: '<div class="sw-product-index"></div>',
                    setup() {
                        listing = useListing({ ...options, getList });
                        callsDuringSetup = getList.mock.calls.length;

                        return {};
                    },
                },
            },
            {
                name: 'sw.product.detail',
                path: '/sw/product/detail',
                component: { template: '<div></div>' },
            },
            {
                name: 'sw.bulk.edit.product',
                path: '/sw/bulk/edit/product',
                component: { template: '<div></div>' },
            },
        ],
        history: createWebHashHistory(),
    });

    await router.push(initialRoute);

    mount({ template: '<router-view />' }, { global: { plugins: [router] } });
    await flushPromises();

    return { listing: listing as UseListingReturn, getList, router, callsDuringSetup };
}

describe('src/app/composables/use-listing', () => {
    beforeEach(() => {
        jest.spyOn(Shopware, 'Service').mockImplementation(((name: string) =>
            name === 'searchRankingService' ? searchRankingService : undefined) as typeof Shopware.Service);

        Shopware.Store.get('shopwareApps').selectedIds = [];
        Shopware.Store.get('swBulkEdit').selectedIds = [];
    });

    afterEach(() => {
        jest.restoreAllMocks();
    });

    it('starts from the listing defaults', async () => {
        const { listing } = await mountListing();

        expect(listing.page.value).toBe(1);
        expect(listing.limit.value).toBe(25);
        expect(listing.total.value).toBe(0);
        expect(listing.sortBy.value).toBeNull();
        expect(listing.sortDirection.value).toBe('ASC');
        expect(listing.naturalSorting.value).toBe(false);
        expect(listing.term.value).toBeUndefined();
        expect(listing.disableRouteParams.value).toBe(false);
    });

    it('takes the initial state from the given options', async () => {
        const { listing } = await mountListing({
            limit: 10,
            sortBy: 'position',
            sortDirection: 'DESC',
            searchConfigEntity: 'product',
            storeKey: 'grid.filter.product',
            filterCriteria: ['stored'],
        });

        expect(listing.limit.value).toBe(10);
        expect(listing.sortBy.value).toBe('position');
        expect(listing.sortDirection.value).toBe('DESC');
        expect(listing.searchConfigEntity.value).toBe('product');
        expect(listing.storeKey.value).toBe('grid.filter.product');
        expect(listing.filterCriteria.value).toEqual(['stored']);
    });

    // Where the mixin loaded from `created()`, the composable waits for `onMounted`, so that a
    // `getList` closing over bindings declared below the call is fully initialized when it runs.
    it('runs the initial load after mount instead of during setup', async () => {
        const { getList, callsDuringSetup } = await mountListing();

        expect(callsDuringSetup).toBe(0);
        expect(getList).toHaveBeenCalledTimes(1);
    });

    it('writes the listing state into the route when the route has no query yet', async () => {
        const { router, getList } = await mountListing();

        expect(router.currentRoute.value.query).toEqual({
            limit: '25',
            page: '1',
            sortBy: null,
            sortDirection: 'ASC',
            naturalSorting: false,
        });
        expect(getList).toHaveBeenCalled();
    });

    it('reads page, limit and sorting from the route query on mount', async () => {
        const { listing, getList } = await mountListing({}, {
            name: 'sw.product.index',
            query: { page: '3', limit: '50', sortBy: 'name', sortDirection: 'DESC', naturalSorting: 'true' },
        } as RouteLocationRaw);

        expect(listing.page.value).toBe(3);
        expect(listing.limit.value).toBe(50);
        expect(listing.sortBy.value).toBe('name');
        expect(listing.sortDirection.value).toBe('DESC');
        expect(listing.naturalSorting.value).toBe(true);
        expect(getList).toHaveBeenCalledTimes(1);
    });

    it('loads without touching the route when route params are disabled', async () => {
        const { listing, router, getList } = await mountListing({ disableRouteParams: true });

        expect(getList).toHaveBeenCalledTimes(1);
        expect(router.currentRoute.value.query).toEqual({});

        listing.onSearch('shirt');
        expect(listing.page.value).toBe(1);
        expect(listing.term.value).toBe('shirt');
        expect(getList).toHaveBeenCalledTimes(2);
        expect(router.currentRoute.value.query).toEqual({});
    });

    it('derives the page count from the total and the limit', async () => {
        const { listing } = await mountListing({ limit: 10 });

        // The component's own getList assigns the total through the returned ref.
        listing.total.value = 42;

        expect(listing.maxPage.value).toBe(5);
    });

    it('publishes the selection to the app and bulk edit stores', async () => {
        const { listing } = await mountListing();

        listing.updateSelection({ 'id-1': {}, 'id-2': {} });
        await flushPromises();

        expect(listing.selectionArray.value).toHaveLength(2);
        expect(listing.selectionCount.value).toBe(2);
        expect(Shopware.Store.get('shopwareApps').selectedIds).toEqual([
            'id-1',
            'id-2',
        ]);
        expect(Shopware.Store.get('swBulkEdit').selectedIds).toEqual([
            'id-1',
            'id-2',
        ]);
    });

    it('suspends the current sorting while a fresh search term is set', async () => {
        const { listing } = await mountListing({ sortBy: 'name' });

        listing.term.value = 'shirt';
        await flushPromises();

        expect(listing.freshSearchTerm.value).toBe(true);
        expect(listing.currentSortBy.value).toBeNull();

        listing.sortBy.value = 'price';
        await flushPromises();

        expect(listing.freshSearchTerm.value).toBe(false);
        expect(listing.currentSortBy.value).toBe('price');
    });

    it('pushes the search term and the first page into the route', async () => {
        const { listing, router } = await mountListing();

        listing.onSearch('shirt');
        await flushPromises();

        expect(router.currentRoute.value.query).toMatchObject({ term: 'shirt', page: '1' });
    });

    it('resets the search term when the same listing route is opened again without a query', async () => {
        const { listing, router } = await mountListing();

        listing.onSearch('shirt');
        await flushPromises();
        expect(listing.term.value).toBe('shirt');

        // Opening the already active admin menu item navigates to the listing route without a query.
        await router.push({ name: 'sw.product.index' });
        await flushPromises();

        expect(listing.term.value).toBeUndefined();
        expect(listing.page.value).toBe(1);
        expect(router.currentRoute.value.query.term).toBeUndefined();
    });

    it('toggles the sort direction when the same column is sorted twice', async () => {
        const { listing, router } = await mountListing({ sortBy: 'name' });

        listing.onSortColumn({ dataIndex: 'name', naturalSorting: false });
        await flushPromises();
        expect(router.currentRoute.value.query.sortDirection).toBe('DESC');

        listing.sortDirection.value = 'DESC';
        listing.onSortColumn({ dataIndex: 'name', naturalSorting: false });
        await flushPromises();
        expect(router.currentRoute.value.query.sortDirection).toBe('ASC');
    });

    it('reloads the list on refresh and on a sorting change', async () => {
        const { listing, getList } = await mountListing();
        const callsAfterMount = getList.mock.calls.length;

        listing.onRefresh();
        expect(getList).toHaveBeenCalledTimes(callsAfterMount + 1);

        listing.onSort({ sortBy: 'name', sortDirection: 'DESC' });
        expect(getList).toHaveBeenCalledTimes(callsAfterMount + 2);
    });

    it('reloads the list when the route query changes', async () => {
        const { router, getList } = await mountListing();
        const callsAfterMount = getList.mock.calls.length;

        await router.push({
            name: 'sw.product.index',
            query: { ...router.currentRoute.value.query, page: '2' },
        });
        await flushPromises();

        expect(getList).toHaveBeenCalledTimes(callsAfterMount + 1);
    });

    it('stores new filter criteria and goes back to the first page', async () => {
        const { listing, router } = await mountListing();
        const criteria = [{ field: 'active' }];

        listing.page.value = 4;
        listing.updateCriteria(criteria);
        await flushPromises();

        expect(listing.page.value).toBe(1);
        expect(listing.filterCriteria.value).toStrictEqual(criteria);
        expect(router.currentRoute.value.query.page).toBe('1');
    });

    it('drops the filter criteria when the stored filter query parameter changes', async () => {
        const { listing, router } = await mountListing({
            storeKey: 'grid.filter.product',
            filterCriteria: [{ field: 'active' }],
        });

        await router.push({
            name: 'sw.product.index',
            query: { ...router.currentRoute.value.query, 'grid.filter.product': 'changed' },
        });
        await flushPromises();

        expect(listing.filterCriteria.value).toEqual([]);
    });

    it('toggles a filter provided by the component and returns to the first page', async () => {
        const filters = [{ name: 'term', active: false }];
        const { listing } = await mountListing({ filters: () => filters });

        listing.page.value = 3;
        listing.onSwitchFilter(filters[0], 0);

        expect(filters[0].active).toBe(true);
        expect(listing.page.value).toBe(1);
    });

    it('clears the store selections when leaving for a regular route', async () => {
        const { listing, router } = await mountListing();

        listing.updateSelection({ 'id-1': {} });
        await flushPromises();

        await router.push({ name: 'sw.product.detail' });
        await flushPromises();

        expect(Shopware.Store.get('shopwareApps').selectedIds).toEqual([]);
        expect(Shopware.Store.get('swBulkEdit').selectedIds).toEqual([]);
    });

    it('keeps the store selections when leaving for a bulk edit route', async () => {
        const { listing, router } = await mountListing();

        listing.updateSelection({ 'id-1': {} });
        await flushPromises();

        await router.push({ name: 'sw.bulk.edit.product' });
        await flushPromises();

        expect(Shopware.Store.get('shopwareApps').selectedIds).toEqual(['id-1']);
        expect(Shopware.Store.get('swBulkEdit').selectedIds).toEqual(['id-1']);
    });

    it('reports the listing parameters from the route, or from the state when disabled', async () => {
        const { listing } = await mountListing({}, {
            name: 'sw.product.index',
            query: { page: '2', limit: '10' },
        } as RouteLocationRaw);

        expect(listing.getMainListingParams()).toMatchObject({ page: '2', limit: '10' });

        listing.disableRouteParams.value = true;

        expect(listing.getMainListingParams()).toMatchObject({ page: 2, limit: 10 });
    });

    it('scores the search query with the fields configured for the entity', async () => {
        const scoredCriteria = { scored: true };
        searchRankingService.getSearchFieldsByEntity.mockResolvedValue({ name: 500 });
        searchRankingService.buildSearchQueriesForEntity.mockReturnValue(scoredCriteria);

        const { listing } = await mountListing({ searchConfigEntity: 'product' });
        const originalCriteria = { original: true } as never;

        await expect(listing.addQueryScores('shirt', originalCriteria)).resolves.toBe(scoredCriteria);
        expect(listing.entitySearchable.value).toBe(true);
    });

    it('marks the entity as not searchable when it has no ranking fields', async () => {
        searchRankingService.getSearchFieldsByEntity.mockResolvedValue({});

        const { listing } = await mountListing({ searchConfigEntity: 'product' });
        const originalCriteria = { original: true } as never;

        await expect(listing.addQueryScores('shirt', originalCriteria)).resolves.toBe(originalCriteria);
        expect(listing.entitySearchable.value).toBe(false);
    });

    it('leaves the criteria untouched without a search config entity', async () => {
        const { listing } = await mountListing();
        const originalCriteria = { original: true } as never;

        await expect(listing.addQueryScores('shirt', originalCriteria)).resolves.toBe(originalCriteria);
        expect(listing.searchRankingFields.value).toEqual({});
    });
});
