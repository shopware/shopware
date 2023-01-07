/**
 * @package system-settings
 */
Shopware.Service('privileges')
    .addPrivilegeMappingEntry({
        category: 'additional_permissions',
        parent: null,
        key: 'system',
        roles: {
            core_update: {
                privileges: [
                    'system:core:update',
                    'system_config:read',
                ],
                dependencies: [],
            },
        },
    })
    .addPrivilegeMappingEntry({
        category: 'additional_permissions',
        parent: null,
        key: 'system',
        roles: {
            plugin_maintain: {
                privileges: [
                    'system:plugin:maintain',
                    'plugin:update',
                    'system:clear:cache',
                    'system_config:read',
                ],
                dependencies: [],
            },
            plugin_upload: {
                privileges: [
                    'user_config:read',
                    'user_config:update',
                    'user_config:create',
                ],
                dependencies: ['system.plugin_maintain'],
            },
        },
    });
