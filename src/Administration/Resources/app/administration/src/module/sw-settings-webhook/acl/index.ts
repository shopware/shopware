/**
 * @sw-package framework
 */
Shopware.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'settings',
    key: 'webhook',
    roles: {
        viewer: {
            privileges: [
                'webhook:read',
                'webhook_event_log:read',
                'app:read',
                'user:read',
                'integration:read',
                'acl_role:read',
            ],
            dependencies: [],
        },
        editor: {
            privileges: ['webhook:update'],
            dependencies: ['webhook.viewer'],
        },
        deleter: {
            privileges: ['webhook:delete'],
            dependencies: ['webhook.viewer'],
        },
    },
});
