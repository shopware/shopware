Shopware.Service('privileges')
    .addPrivilegeMappingEntry({
        category: 'permissions',
        parent: null,
        key: 'property',
        roles: {
            viewer: {
                privileges: [
                    'property_group_option:read',
                    'property_group:read',
                    'media_default_folder:read',
                    'media_folder:read',
                    'media:read'
                ],
                dependencies: []
            },
            editor: {
                privileges: [
                    'property_group_option:update',
                    'property_group:update',
                    'media:create'
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
