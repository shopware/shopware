/**
 * @sw-package checkout
 */

import CUSTOMER from '../constant/sw-customer.constant';

type NameableCustomer = {
    accountType?: string;
    firstName?: string;
    lastName?: string;
    company?: string | null;
};

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default function customerDisplayName(customer?: NameableCustomer | null, lastNameFirst = false): string {
    if (!customer) {
        return '';
    }

    const company = (customer.company ?? '').trim();

    if (company !== '' && customer.accountType === CUSTOMER.ACCOUNT_TYPE_BUSINESS) {
        return company;
    }

    const firstName = (customer.firstName ?? '').trim();
    const lastName = (customer.lastName ?? '').trim();

    if (!lastNameFirst) {
        return `${firstName} ${lastName}`.trim();
    }

    if (firstName === '' || lastName === '') {
        return `${lastName}${firstName}`.trim();
    }

    return `${lastName}, ${firstName}`;
}
