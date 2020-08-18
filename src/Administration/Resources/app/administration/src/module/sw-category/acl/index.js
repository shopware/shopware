Shopware.Service('privileges')
    .addPrivilegeMappingEntry({
        category: 'permissions',
        parent: null,
        key: 'category',
        roles: {
            viewer: {
                privileges: [
                    'category:read',
                    'media_folder:read',
                    'media:read',
                    'seo_url:read',
                    'tag:read',
                    'sales_channel:read',
                    'media_default_folder:read',
                    'product:read',
                    'property_group_option:read',
                    'property_group:read',
                    'product_manufacturer:read',
                    'sales_channel_type:read',
                    'cms_page:read',
                    'cms_section:read',
                    'cms_block:read',
                    'cms_slot:read',
                    'custom_field_set:read',
                    'custom_field:read',
                    'custom_field_set_relation:read'
                ],
                dependencies: []
            },
            editor: {
                privileges: [
                    'category:update',
                    'media:create',
                    'media:delete',
                    'media_thumbnail:delete',
                    'product_category:create'
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
