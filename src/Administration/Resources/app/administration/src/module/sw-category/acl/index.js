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
                    'currency:read',
                ],
                dependencies: [],
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
                    'category_tag:delete',
                ],
                dependencies: [
                    'category.viewer',
                ],
            },
            creator: {
                privileges: [
                    'category:create',
                ],
                dependencies: [
                    'category.viewer',
                    'category.editor',
                ],
            },
            deleter: {
                privileges: [
                    'category:delete',
                ],
                dependencies: [
                    'category.viewer',
                ],
            },
        },
    })
    .addPrivilegeMappingEntry({
        category: 'permissions',
        parent: 'catalogues',
        key: 'landing_page',
        roles: {
            viewer: {
                privileges: [
                    'landing_page:read',
                    'landing_page_translation:read',
                    'landing_page_tag:read',
                    'landing_page_sales_channel:read',
                    Shopware.Service('privileges').getPrivileges('media.viewer'),
                    'tag:read',
                    'sales_channel:read',
                    'sales_channel_type:read',
                    Shopware.Service('privileges').getPrivileges('cms.viewer'),
                    'custom_field_set:read',
                    'custom_field:read',
                    'custom_field_set_relation:read',
                ],
                dependencies: [],
            },
            editor: {
                privileges: [
                    'landing_page:update',
                    'landing_page_translation:create',
                    'landing_page_translation:update',
                    Shopware.Service('privileges').getPrivileges('media.creator'),
                    Shopware.Service('privileges').getPrivileges('cms.editor'),
                    'tag:create',
                    'landing_page_tag:create',
                    'landing_page_tag:delete',
                    'landing_page_sales_channel:create',
                    'landing_page_sales_channel:delete',
                ],
                dependencies: [
                    'category.viewer',
                ],
            },
            creator: {
                privileges: [
                    'landing_page:create',
                ],
                dependencies: [
                    'landing_page.viewer',
                    'landing_page.editor',
                ],
            },
            deleter: {
                privileges: [
                    'landing_page:delete',
                ],
                dependencies: [
                    'landing_page.viewer',
                ],
            },
        },
    });
