/**
 * @sw-package checkout
 */

import customerDisplayName from './customer-display-name.helper';

describe('module/sw-customer/helper/customer-display-name.helper', () => {
    it.each([
        ['private account uses the person name', 'private', 'Ada', 'Lovelace', null, 'Ada Lovelace'],
        ['private account ignores the company', 'private', 'Ada', 'Lovelace', 'Acme GmbH', 'Ada Lovelace'],
        ['company account uses the company', 'business', 'Ada', 'Lovelace', 'Acme GmbH', 'Acme GmbH'],
        ['company account without a contact person uses the company', 'business', '', '', 'Acme GmbH', 'Acme GmbH'],
        ['company account without a company falls back to the person name', 'business', 'Ada', 'Lovelace', null, 'Ada Lovelace'],
        ['company account with a blank company falls back to the person name', 'business', 'Ada', 'Lovelace', '   ', 'Ada Lovelace'],
        ['an empty person name is not padded with a space', 'private', '', '', null, ''],
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
});
