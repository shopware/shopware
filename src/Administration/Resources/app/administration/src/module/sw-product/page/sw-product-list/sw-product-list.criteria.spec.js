/**
 * @sw-package inventory
 */

import swProductList from './index';

describe('module/sw-product/page/sw-product-list criteria', () => {
    it('adds a deterministic secondary sort when sorting by stock', () => {
        const criteria = swProductList.computed.productCriteria.call({
            page: 1,
            limit: 25,
            term: null,
            sortBy: 'stock',
            sortDirection: 'ASC',
            naturalSorting: false,
            filterCriteria: [],
        });

        expect(criteria.sortings).toEqual([
            expect.objectContaining({
                field: 'stock',
                order: 'ASC',
            }),
            expect.objectContaining({
                field: 'id',
                order: 'ASC',
            }),
        ]);
    });
});
