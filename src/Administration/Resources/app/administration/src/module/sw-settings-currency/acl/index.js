Shopware.Service('privileges')
    .addPrivilegeMappingEntry({
        category: 'permissions',
        parent: 'settings',
        key: 'currencies',
        roles: {
            viewer: {
                privileges: [
                    'currency:read',
                    'user_config:read',
                    'user_config:create',
                    'user_config:update'
                ],
                dependencies: []
            },
            editor: {
                privileges: [
                    'currency:update'
                ],
                dependencies: [
                    'currencies.viewer'
                ]
            },
            creator: {
                privileges: [
                    'currency:create'
                ],
                dependencies: [
                    'currencies.viewer',
                    'currencies.editor'
                ]
            },
            deleter: {
                privileges: [
                    'currency:delete'
                ],
                dependencies: [
                    'currencies.viewer'
                ]
            }
        }
    });
