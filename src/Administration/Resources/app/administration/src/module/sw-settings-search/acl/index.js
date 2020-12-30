Shopware.Service('privileges')
    .addPrivilegeMappingEntry({
        category: 'permissions',
        parent: 'settings',
        key: 'product_search_config',
        roles: {
            viewer: {
                privileges: [
                    'product_search_config:read'
                ],
                dependencies: []
            },
            editor: {
                privileges: [
                    'product_search_config:update'
                ],
                dependencies: [
                    'product_search_config.viewer'
                ]
            },
            creator: {
                privileges: [
                    'product_search_config:create'
                ],
                dependencies: [
                    'product_search_config.viewer',
                    'product_search_config.editor'
                ]
            },
            deleter: {
                privileges: [
                    'product_search_config:delete'
                ],
                dependencies: [
                    'product_search_config.viewer'
                ]
            }
        }
    });
