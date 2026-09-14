/**
 * @sw-package checkout
 */

import customerDisplayName, { customerAvatarName } from './customer-display-name.helper';

describe('core/helper/customer-display-name.helper', () => {
    it.each([
        [
            'private account uses the person name',
            'private',
            'Ada',
            'Lovelace',
            null,
            'Ada Lovelace',
        ],
        [
            'private account ignores the company',
            'private',
            'Ada',
            'Lovelace',
            'Acme GmbH',
            'Ada Lovelace',
        ],
        [
            'company account keeps an existing contact person',
            'business',
            'Ada',
            'Lovelace',
            'Acme GmbH',
            'Ada Lovelace',
        ],
        [
            'company account without a contact person uses the company',
            'business',
            '',
            '',
            'Acme GmbH',
            'Acme GmbH',
        ],
        [
            'company account without a company falls back to the person name',
            'business',
            'Ada',
            'Lovelace',
            null,
            'Ada Lovelace',
        ],
        [
            'company account with a blank company falls back to the person name',
            'business',
            'Ada',
            'Lovelace',
            '   ',
            'Ada Lovelace',
        ],
        [
            'an empty person name is not padded with a space',
            'private',
            '',
            '',
            null,
            '',
        ],
    ])('%s', (_name, accountType, firstName, lastName, company, expected) => {
        expect(
            customerDisplayName({
                accountType,
                firstName,
                lastName,
                company,
            }),
        ).toBe(expected);
    });

    it('returns an empty string without a customer', () => {
        expect(customerDisplayName(null)).toBe('');
        expect(customerDisplayName(undefined)).toBe('');
    });

    describe('customerAvatarName', () => {
        it('keeps the raw name fields of a contact person', () => {
            expect(
                customerAvatarName({
                    accountType: 'business',
                    firstName: ' Ada ',
                    lastName: 'van Halen',
                    company: 'Acme GmbH',
                }),
            ).toEqual({ firstName: 'Ada', lastName: 'van Halen' });
        });

        it('takes the first and the last word of the company of a nameless company account', () => {
            expect(
                customerAvatarName({
                    accountType: 'business',
                    firstName: '',
                    lastName: '',
                    company: 'Acme Holding GmbH',
                }),
            ).toEqual({ firstName: 'Acme', lastName: 'GmbH' });
        });

        it('leaves the last name empty for a one word company', () => {
            expect(
                customerAvatarName({
                    accountType: 'business',
                    firstName: '',
                    lastName: '',
                    company: 'Acme',
                }),
            ).toEqual({ firstName: 'Acme', lastName: '' });
        });

        it('returns empty names without a customer', () => {
            expect(customerAvatarName(null)).toEqual({ firstName: '', lastName: '' });
        });
    });
});
