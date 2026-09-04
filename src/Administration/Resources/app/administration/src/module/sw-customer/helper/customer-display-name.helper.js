/**
 * @sw-package checkout
 */

import CUSTOMER from '../constant/sw-customer.constant';

/**
 * The account holder of a company account is the legal entity, so it is named by its company.
 * Mirrors `CustomerEntity::getDisplayName()`.
 *
 * @param {{ accountType?: string, firstName?: string, lastName?: string, company?: string }} customer
 * @returns {string}
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default function customerDisplayName(customer) {
    if (!customer) {
        return '';
    }

    const company = (customer.company || '').trim();

    if (company !== '' && customer.accountType === CUSTOMER.ACCOUNT_TYPE_BUSINESS) {
        return company;
    }

    return `${customer.firstName || ''} ${customer.lastName || ''}`.trim();
}
