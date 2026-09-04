/**
 * @sw-package checkout
 */

import orderCustomerName from './order-customer-name.helper';

describe('module/sw-order/helper/order-customer-name.helper', () => {
    it.each([
        [
            'person name only',
            'Ada',
            'Lovelace',
            null,
            'Ada Lovelace',
        ],
        [
            'person name and company are appended',
            'Ada',
            'Lovelace',
            'Acme GmbH',
            'Ada Lovelace - Acme GmbH',
        ],
        [
            'a company already in the name is not repeated',
            '',
            'Acme GmbH',
            'Acme GmbH',
            'Acme GmbH',
        ],
        [
            'no contact person falls back to the company',
            '',
            '',
            'Acme GmbH',
            'Acme GmbH',
        ],
        [
            'nothing at all stays empty',
            '',
            '',
            null,
            '',
        ],
    ])('%s', (_name, firstName, lastName, company, expected) => {
        expect(orderCustomerName({ firstName, lastName, company })).toBe(expected);
    });

    it.each([
        [
            'person name is reversed',
            'Ada',
            'Lovelace',
            null,
            'Lovelace, Ada',
        ],
        [
            'a company already in the name is not repeated',
            '',
            'Acme GmbH',
            'Acme GmbH',
            'Acme GmbH',
        ],
        [
            'no contact person falls back to the company',
            '',
            '',
            'Acme GmbH',
            'Acme GmbH',
        ],
        [
            'a single name is not preceded by a comma',
            '',
            'Lovelace',
            null,
            'Lovelace',
        ],
    ])('with the last name first: %s', (_name, firstName, lastName, company, expected) => {
        expect(orderCustomerName({ firstName, lastName, company }, true)).toBe(expected);
    });

    it('returns an empty string without an order customer', () => {
        expect(orderCustomerName(null)).toBe('');
        expect(orderCustomerName(undefined)).toBe('');
    });
});
