/**
 * @sw-package checkout
 */

import swCustomerList from './index';

describe('sw-customer-list criteria', () => {
    it('defaults to indexed insertion order', () => {
        const criteria = swCustomerList.computed.defaultCriteria.call({
            ...swCustomerList.data(),
            page: 1,
            limit: 25,
            term: null,
        });

        expect(criteria.sortings).toEqual([
            expect.objectContaining({ field: 'autoIncrement', order: 'DESC', naturalSorting: false }),
        ]);
    });
});
