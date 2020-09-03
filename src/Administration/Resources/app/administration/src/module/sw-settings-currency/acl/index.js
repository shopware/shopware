Shopware.Service('privileges')
    .addPrivilegeMappingEntry({
        category: 'permissions',
        parent: 'settings',
        key: 'currencies',
        roles: {
            viewer: {
                privileges: [
                    'currency:read'
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
