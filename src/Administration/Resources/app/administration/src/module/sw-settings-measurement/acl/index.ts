/**
 * @sw-package inventory
 */
Shopware.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'settings',
    key: 'measurement',
    roles: {
        viewer: {
            privileges: [
                'system_config:read',
            ],
            dependencies: [],
        },
        editor: {
            privileges: [
                'system_config:read',
                'system_config:create',
                'system_config:update',
                'system_config:delete',
            ],
            dependencies: [
                'measurement.viewer',
            ],
        },
        creator: {
            privileges: [
                'system_config:read',
                'system_config:create',
                'system_config:update',
                'system_config:delete',
            ],
            dependencies: [
                'measurement.viewer',
                'measurement.editor',
            ],
        },
        deleter: {
            privileges: [
                'system_config:read',
                'system_config:create',
                'system_config:update',
                'system_config:delete',
            ],
            dependencies: [
                'measurement.viewer',
            ],
        },
    },
});
