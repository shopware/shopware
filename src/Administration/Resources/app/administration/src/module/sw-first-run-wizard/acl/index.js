Shopware.Service('privileges')
    .addPrivilegeMappingEntry({
        category: 'additional_permissions',
        parent: null,
        key: 'system',
        roles: {
            frw: {
                privileges: [
                    Shopware.Service('privileges').getPrivileges('system.plugin_maintain'),
                    'user:read',
                    'snippet_set:read',
                    'system_config:read',
                    'system_config:update',
                    'system_config:create',
                    'system_config:delete'
                ],
                dependencies: []
            }
        }
    });
