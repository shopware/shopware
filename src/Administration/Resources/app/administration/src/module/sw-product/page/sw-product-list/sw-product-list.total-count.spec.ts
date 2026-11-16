/**
 * @sw-package inventory
 */

import type CriteriaType from 'src/core/data/criteria.data';
import swProductList from './index';

const { Criteria } = Shopware.Data;

type ProductPage = Array<{ id: string }> & { total: number; criteria: CriteriaType };

type ListingContext = {
    page: number;
    limit: number;
    term: string | null;
    total: number;
    totalCountKey: string | null;
    filterCriteria: unknown[];
    productCriteria: CriteriaType;
    productRepository: { search: jest.Mock<Promise<ProductPage>, [CriteriaType]> };
    countModes: number[];
    getList: () => Promise<void>;
    onRefresh: () => void;
    [key: string]: unknown;
};

function productPage(rows: number, total: number, criteria: CriteriaType): ProductPage {
    const page = Array.from({ length: rows }, (_, i) => ({ id: `product-${i}` })) as ProductPage;
    page.total = total;
    page.criteria = criteria;

    return page;
}

function createListing(storedTotal: number): ListingContext {
    const listing = {
        page: 1,
        limit: 25,
        term: null,
        total: 0,
        totalCountKey: null,
        isLoading: false,
        freshSearchTerm: false,
        storeKey: 'grid.filter.product',
        entitySearchable: true,
        adminEsEnable: true,
        productTypes: ['physical', 'digital'],
        filterCriteria: [],
        selection: {},
        normalizeCategoryFilters: (filters: unknown[]) => filters,
        repositoryFactory: { create: () => ({ search: () => Promise.resolve([]) }) },
        countModes: [] as number[],
        productRepository: {
            search: jest.fn((criteria: CriteriaType) => {
                listing.countModes.push(Number(criteria.totalCountMode));
                const offset = (listing.page - 1) * listing.limit;
                const rows = Math.max(0, Math.min(listing.limit, storedTotal - offset));
                const total = Number(criteria.totalCountMode) === 1 ? storedTotal : rows;

                return Promise.resolve(productPage(rows, total, criteria));
            }),
        },
    } as unknown as ListingContext;

    Object.defineProperty(listing, 'productCriteria', {
        get: () => {
            const criteria = new Criteria(listing.page, listing.limit);
            listing.filterCriteria.forEach((filter) => criteria.addFilter(filter as never));
            criteria.addSorting(Criteria.sort('createdAt', 'DESC'));

            return criteria;
        },
    });

    Object.entries(swProductList.methods ?? {}).forEach(([name, method]) => {
        listing[name] = (method as (...args: unknown[]) => unknown).bind(listing);
    });

    return listing;
}

function countModes(listing: ListingContext): number[] {
    return listing.countModes;
}

describe('module/sw-product/page/sw-product-list total count', () => {
    beforeEach(() => {
        const service = Shopware.Service;
        jest.spyOn(Shopware, 'Service').mockImplementation(((name: string) => {
            if (name === 'filterService') {
                return { mergeWithStoredFilters: (_storeKey: string, criteria: CriteriaType) => criteria };
            }

            return service(name as never);
        }) as typeof Shopware.Service);
    });

    afterEach(() => {
        jest.restoreAllMocks();
    });

    it('counts the products only once while paging through the same filters', async () => {
        const listing = createListing(60);

        await listing.getList();
        listing.page = 2;
        await listing.getList();

        expect(countModes(listing)).toEqual([1, 0]);
        expect(listing.total).toBe(60);
    });

    it('counts again when the filters change', async () => {
        const listing = createListing(60);

        await listing.getList();
        listing.filterCriteria = [Criteria.equals('active', true)];
        listing.page = 2;
        await listing.getList();

        expect(countModes(listing)).toEqual([1, 1]);
    });

    it('takes the total from a short last page', async () => {
        const listing = createListing(60);

        await listing.getList();
        listing.total = 70;
        listing.page = 3;
        await listing.getList();

        expect(countModes(listing)).toEqual([1, 0]);
        expect(listing.total).toBe(60);
    });

    it('counts again when a page behind the known total comes back empty', async () => {
        const listing = createListing(30);

        await listing.getList();
        listing.total = 60;
        listing.page = 3;
        await listing.getList();
        await flushPromises();

        expect(countModes(listing)).toEqual([1, 0, 1]);
        expect(listing.total).toBe(30);
    });

    it('always counts a search with a term', async () => {
        const listing = createListing(60);
        listing.term = 'shirt';
        listing.addQueryScores = (_term: string, criteria: CriteriaType) => Promise.resolve(criteria);

        await listing.getList();
        listing.page = 2;
        await listing.getList();

        expect(countModes(listing).every((mode) => mode === 1)).toBe(true);
    });

    it('counts again on refresh', async () => {
        const listing = createListing(60);

        await listing.getList();
        listing.onRefresh();
        await flushPromises();

        expect(countModes(listing)).toEqual([1, 1]);
    });

    it('hands an exactly counting criteria to the grid after reusing the total', async () => {
        const listing = createListing(60);

        await listing.getList();
        listing.page = 2;
        await listing.getList();

        expect(Number((listing.products as ProductPage).criteria.totalCountMode)).toBe(1);
    });

    it('hands the known total to the grid after reusing it', async () => {
        const listing = createListing(60);

        await listing.getList();
        listing.page = 2;
        await listing.getList();

        expect((listing.products as ProductPage).total).toBe(60);
    });
});
