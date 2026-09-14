/**
 * @sw-package checkout
 */

import { orderCustomerBuyerName, orderCustomerDisplayName } from './order-customer-name.helper';

describe('core/helper/order-customer-name.helper', () => {
    describe('orderCustomerDisplayName', () => {
        it.each([
            [
                'person name only',
                'Ada',
                'Lovelace',
                null,
                'Ada Lovelace',
            ],
            [
                'the company is never appended to a contact person',
                'Ada',
                'Lovelace',
                'Acme GmbH',
                'Ada Lovelace',
            ],
            [
                'no contact person falls back to the company',
                '',
                '',
                'Acme GmbH',
                'Acme GmbH',
            ],
            [
                'a blank contact person falls back to the company',
                '   ',
                ' ',
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
            expect(orderCustomerDisplayName({ firstName, lastName, company })).toBe(expected);
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
            expect(orderCustomerDisplayName({ firstName, lastName, company }, true)).toBe(expected);
        });

        it('returns an empty string without an order customer', () => {
            expect(orderCustomerDisplayName(null)).toBe('');
            expect(orderCustomerDisplayName(undefined)).toBe('');
        });
    });

    describe('orderCustomerBuyerName', () => {
        it.each([
            [
                'person name only',
                'Ada',
                'Lovelace',
                null,
                'Ada Lovelace',
            ],
            [
                'the company is appended to the contact person',
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
            expect(orderCustomerBuyerName({ firstName, lastName, company })).toBe(expected);
        });

        it('returns an empty string without an order customer', () => {
            expect(orderCustomerBuyerName(null)).toBe('');
            expect(orderCustomerBuyerName(undefined)).toBe('');
        });
    });
});
