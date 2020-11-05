Shopware.Service('privileges')
    .addPrivilegeMappingEntry({
        category: 'permissions',
        parent: 'catalogues',
        key: 'category',
        roles: {
            viewer: {
                privileges: [
                    'category:read',
                    Shopware.Service('privileges').getPrivileges('media.viewer'),
                    'seo_url:read',
                    'tag:read',
                    'sales_channel:read',
                    'product:read',
                    'property_group_option:read',
                    'property_group:read',
                    'product_manufacturer:read',
                    'sales_channel_type:read',
                    Shopware.Service('privileges').getPrivileges('cms.viewer'),
                    'custom_field_set:read',
                    'custom_field:read',
                    'custom_field_set_relation:read',
                    'product_stream:read',
                    'currency:read'
                ],
                dependencies: []
            },
            editor: {
                privileges: [
                    'category:update',
                    'media:delete',
                    'media_thumbnail:delete',
                    Shopware.Service('privileges').getPrivileges('media.creator'),
                    Shopware.Service('privileges').getPrivileges('cms.editor'),
                    'product_category:create',
                    'tag:create',
                    'category_tag:create',
                    'category_tag:delete'
                ],
                dependencies: [
                    'category.viewer'
                ]
            },
            creator: {
                privileges: [
                    'category:create'
                ],
                dependencies: [
                    'category.viewer',
                    'category.editor'
                ]
            },
            deleter: {
                privileges: [
                    'category:delete'
                ],
                dependencies: [
                    'category.viewer'
                ]
            }
        }
    });
