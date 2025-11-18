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
                'measurement_system:read',
                'measurement_display_unit:read',
            ],
            dependencies: [],
        },
        editor: {
            privileges: [
                'system_config:update',
            ],
            dependencies: [
                'measurement.viewer',
            ],
        },
        creator: {
            privileges: [
                'measurement_system:create',
                'measurement_display_unit:create',
            ],
            dependencies: [
                'measurement.viewer',
                'measurement.editor',
            ],
        },
        deleter: {
            privileges: [
                'measurement_system:delete',
                'measurement_display_unit:delete',
            ],
            dependencies: [
                'measurement.viewer',
            ],
        },
    },
});
