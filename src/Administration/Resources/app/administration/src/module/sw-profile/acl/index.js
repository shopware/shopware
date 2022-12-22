/**
 * @package system-settings
 */
Shopware.Service('privileges')
    .addPrivilegeMappingEntry({
        category: 'additional_permissions',
        parent: null,
        key: 'user',
        roles: {
            update_profile: {
                privileges: [
                    'user_change_me',
                    'user:read',
                    'user_config:read',
                    'user_config:update',
                    'user_config:create',
                ],
                dependencies: [],
            },
        },
    });
