/**
 * @sw-package inventory
 */

import swProductList from './index';

function buildCriteria(overrides = {}) {
    return swProductList.computed.productCriteria.call({
        page: 1,
        limit: 25,
        term: null,
        sortBy: 'stock',
        sortDirection: 'ASC',
        naturalSorting: false,
        filterCriteria: [],
        ...overrides,
    });
}

describe('module/sw-product/page/sw-product-list criteria', () => {
    it('defaults to indexed insertion order without a redundant tie-breaker', () => {
        const criteria = buildCriteria(swProductList.data.call({ $t: (key) => key }));

        expect(criteria.sortings).toEqual([
            expect.objectContaining({ field: 'autoIncrement', order: 'DESC', naturalSorting: false }),
        ]);
    });

    it('keeps natural sorting for the product number column', () => {
        const columns = swProductList.methods.getProductColumns.call({ $t: (key) => key, currenciesColumns: [] });

        expect(columns.find((column) => column.property === 'productNumber').naturalSorting).toBe(true);
        expect(columns.some((column) => column.property === 'autoIncrement')).toBe(false);
    });

    it('appends a deterministic id tie-breaker when sorting by stock', () => {
        const criteria = buildCriteria({ sortBy: 'stock' });

        expect(criteria.sortings).toEqual([
            expect.objectContaining({ field: 'stock', order: 'ASC' }),
            expect.objectContaining({ field: 'id', order: 'ASC' }),
        ]);
    });

    it('appends the id tie-breaker for any other sort field', () => {
        const criteria = buildCriteria({ sortBy: 'createdAt', sortDirection: 'DESC' });

        expect(criteria.sortings).toEqual([
            expect.objectContaining({ field: 'createdAt', order: 'DESC' }),
            expect.objectContaining({ field: 'id', order: 'ASC' }),
        ]);
    });

    it('does not duplicate the id tie-breaker when already sorting by id', () => {
        const criteria = buildCriteria({ sortBy: 'id', sortDirection: 'DESC' });

        expect(criteria.sortings).toEqual([expect.objectContaining({ field: 'id', order: 'DESC' })]);
    });
});
