/**
 * @sw-package framework
 */
Shopware.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'settings',
    key: 'webhook',
    roles: {
        viewer: {
            // the owners and roles of the webhooks are shown in the list
            privileges: [
                'webhook:read',
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

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export {};
