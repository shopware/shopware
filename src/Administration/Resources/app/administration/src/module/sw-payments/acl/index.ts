/**
 * @sw-package checkout
 */
Shopware.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    key: 'sw-payments',
    parent: 'settings',
    roles: {
        viewer: {
            privileges: ['app.ShopwarePayments'],
            dependencies: ['app.ShopwarePayments', 'payment.viewer'],
        },
        editor: {
            privileges: [],
            dependencies: ['sw-payments.viewer', 'payment.editor'],
        },
        creator: {
            privileges: [],
            dependencies: ['sw-payments.viewer', 'sw-payments.editor'],
        },
    },
});
