Shopware.Service('privileges')
    .addPrivilegeMappingEntry({
        category: 'permissions',
        parent: null,
        key: 'property',
        roles: {
            viewer: {
                privileges: [
                    'property_group_option:read',
                    'property_group:read'
                ],
                dependencies: []
            },
            editor: {
                privileges: [
                    'property_group_option:update',
                    'property_group:update'
                ],
                dependencies: [
                    'property.viewer'
                ]
            },
            creator: {
                privileges: [
                    'property_group_option:create',
                    'property_group:create'
                ],
                dependencies: [
                    'property.viewer',
                    'property.editor'
                ]
            },
            deleter: {
                privileges: [
                    'property_group_option:delete',
                    'property_group:delete'
                ],
                dependencies: [
                    'property.viewer'
                ]
            }
        }
    });
