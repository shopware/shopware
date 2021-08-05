Shopware.Service('privileges')
    .addPrivilegeMappingEntry({
        category: 'additional_permissions',
        parent: null,
        key: 'system',
        roles: {
            logging: {
                privileges: [
                    'log_entry:read',
                    'log_entry:create',
                    'log_entry:update',
                    'log_entry:delete',
                ],
                dependencies: [],
            },
        },
    });
