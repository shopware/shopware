/**
 * @sw-package checkout
 */

type NameableOrderCustomer = {
    firstName?: string;
    lastName?: string;
    company?: string | null;
};

function personName(customer: NameableOrderCustomer, lastNameFirst: boolean): string {
    const firstName = (customer.firstName ?? '').trim();
    const lastName = (customer.lastName ?? '').trim();

    if (!lastNameFirst) {
        return `${firstName} ${lastName}`.trim();
    }

    return [
        lastName,
        firstName,
    ]
        .filter((part) => part !== '')
        .join(', ');
}

/**
 * Mirrors OrderCustomerNameFormatter::displayName().
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export function orderCustomerDisplayName(customer?: NameableOrderCustomer | null, lastNameFirst = false): string {
    if (!customer) {
        return '';
    }

    return personName(customer, lastNameFirst) || (customer.company ?? '').trim();
}

/**
 * Mirrors OrderCustomerNameFormatter::buyerName().
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export function orderCustomerBuyerName(customer?: NameableOrderCustomer | null): string {
    if (!customer) {
        return '';
    }

    const name = personName(customer, false);
    const company = (customer.company ?? '').trim();

    if (company === '' || name === company) {
        return name;
    }

    return name === '' ? company : `${name} - ${company}`;
}
