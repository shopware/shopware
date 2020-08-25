Shopware.Service('privileges')
    .addPrivilegeMappingEntry({
        category: 'permissions',
        parent: null,
        key: 'product_manufacturer',
        roles: {
            viewer: {
                privileges: [
                    'product_manufacturer:read',
                    'custom_field_set:read',
                    'custom_field:read',
                    'custom_field_set_relation:read',
                    'media_default_folder:read'
                ],
                dependencies: []
            },
            editor: {
                privileges: [
                    'product_manufacturer:update',
                    'media_folder:read',
                    'media:read'
                ],
                dependencies: [
                    'product_manufacturer.viewer'
                ]
            },
            creator: {
                privileges: [
                    'product_manufacturer:create'
                ],
                dependencies: [
                    'product_manufacturer.viewer',
                    'product_manufacturer.editor'
                ]
            },
            deleter: {
                privileges: [
                    'product_manufacturer:delete'
                ],
                dependencies: [
                    'product_manufacturer.viewer'
                ]
            }
        }
    });
