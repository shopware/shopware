/**
 * @sw-package checkout
 */

import type Criteria from 'src/core/data/criteria.data';
import swOrderList from './index';

describe('sw-order-list criteria', () => {
    it('defaults to indexed insertion order', () => {
        const criteria = swOrderList.computed.orderCriteria.call({
            ...swOrderList.data(),
            page: 1,
            limit: 25,
            term: null,
        }) as Criteria;

        expect(criteria.sortings).toEqual([
            expect.objectContaining({ field: 'autoIncrement', order: 'DESC', naturalSorting: false }),
        ]);
    });
});
