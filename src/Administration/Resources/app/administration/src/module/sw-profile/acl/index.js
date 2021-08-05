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
                ],
                dependencies: [],
            },
        },
    });
