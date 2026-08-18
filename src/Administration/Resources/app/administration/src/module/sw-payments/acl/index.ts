/**
 * @sw-package checkout
 */
Shopware.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    key: 'sw-payments',
    parent: 'settings',
    roles: {
        viewer: {
            privileges: [
                Shopware.Service('privileges').getPrivileges('payment.viewer'),
                'app.ShopwarePayments',
            ],
            dependencies: [
                'app.ShopwarePayments',
            ],
        },
        editor: {
            privileges: [],
            dependencies: [
                'sw-payments.viewer',
            ],
        },
        creator: {
            privileges: [],
            dependencies: [
                'sw-payments.viewer',
                'sw-payments.editor',
            ],
        },
    },
});
