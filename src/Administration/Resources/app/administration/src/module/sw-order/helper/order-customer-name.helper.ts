/**
 * @sw-package checkout
 */

type NameableOrderCustomer = {
    firstName?: string;
    lastName?: string;
    company?: string | null;
};

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default function orderCustomerName(customer?: NameableOrderCustomer | null, lastNameFirst = false): string {
    if (!customer) {
        return '';
    }

    const firstName = (customer.firstName ?? '').trim();
    const lastName = (customer.lastName ?? '').trim();
    const company = (customer.company ?? '').trim();

    const personName = lastNameFirst
        ? [
              lastName,
              firstName,
          ]
              .filter((part) => part !== '')
              .join(', ')
        : `${firstName} ${lastName}`.trim();

    if (company === '' || personName === company) {
        return personName === '' ? company : personName;
    }

    return personName === '' ? company : `${personName} - ${company}`;
}
