/**
 * @sw-package framework
 */
Shopware.Service('privileges').addPrivilegeMappingEntry({
    category: 'additional_permissions',
    parent: null,
    key: 'system',
    roles: {
        system_health: {
            privileges: ['system:system_health'],
            dependencies: [],
        },
    },
});
