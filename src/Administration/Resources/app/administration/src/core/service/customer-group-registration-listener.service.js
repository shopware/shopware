const { Application, Service } = Shopware;
const { Criteria } = Shopware.Data;


/**
 * @package admin
 *
 * @module core/service/customer-group-registration-listener
 */

/**
 * @package customer-order
 * @memberOf module:core/service/customer-group-registration-listener
 * @method addCustomerGroupRegistrationListener
 * @param loginService
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default function addCustomerGroupRegistrationListener(loginService) {
    let applicationRoot = null;

    loginService.addOnLoginListener(checkForUpdates);

    async function checkForUpdates() {
        if (!Shopware.Service('acl').can('customer.viewer')) {
            return;
        }

        const customerRepository = Service('repositoryFactory').create('customer');
        const criteria = new Criteria(1, 25);
        criteria.addAssociation('requestedGroup');
        criteria.addFilter(Criteria.not('AND', [Criteria.equals('requestedGroupId', null)]));

        const customers = await customerRepository.search(criteria, Shopware.Context.api);

        customers.forEach(createNotification);
    }

    function createNotification(customer) {
        const notification = {
            title: getApplicationRootReference().$tc(
                'global.default.info',
            ),
            message: getApplicationRootReference().$tc(
                'sw-customer.customerGroupRegistration.notification.message',
                0,
                { name: `${customer.firstName} ${customer.lastName}`, groupName: customer.requestedGroup.name },
            ),
            actions: [{
                label: getApplicationRootReference().$tc(
                    'sw-customer.customerGroupRegistration.notification.openCustomer',
                ),
                route: { name: 'sw.customer.detail', params: { id: customer.id } },
            }],
            variant: 'info',
            appearance: 'notification',
            growl: true,
        };

        getApplicationRootReference().$store.dispatch(
            'notification/createNotification',
            notification,
        );
    }

    function getApplicationRootReference() {
        if (!applicationRoot) {
            applicationRoot = Application.getApplicationRoot();
        }

        return applicationRoot;
    }
}
