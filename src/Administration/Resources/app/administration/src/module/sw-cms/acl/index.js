Shopware.Service('privileges')
    .addPrivilegeMappingEntry({
        category: 'permissions',
        parent: 'content',
        key: 'cms',
        roles: {
            viewer: {
                privileges: [
                    'cms_page:read',
                    'media:read',
                    'cms_section:read',
                    'category:read',
                    'landing_page:read',
                    'media_default_folder:read',
                    'media_folder:read',
                    'sales_channel:read',
                    'cms_block:read',
                    'cms_slot:read',
                    'product_sorting:read',
                    'product:read',
                    'property_group:read',
                    'property_group_option:read',
                    'product_media:read',
                    'delivery_time:read',
                    'product_cross_selling:read',
                    'product_cross_selling_assigned_products:read',
                    'product_manufacturer:read',
                ],
                dependencies: [],
            },
            editor: {
                privileges: [
                    'cms_page:update',
                    'cms_section:update',
                    'cms_block:update',
                    'cms_slot:update',
                    'cms_section:delete',
                    'cms_block:delete',
                    'cms_slot:delete',
                    'cms_section:create',
                    'cms_block:create',
                    'cms_slot:create',
                    Shopware.Service('privileges').getPrivileges('media.creator'),
                    'product_stream:read',
                    'currency:read',
                    'product_manufacturer:read',
                    'category:update',
                    'landing_page:update',
                ],
                dependencies: [
                    'cms.viewer',
                ],
            },
            creator: {
                privileges: [
                    'cms_page:create',
                ],
                dependencies: [
                    'cms.viewer',
                    'cms.editor',
                ],
            },
            deleter: {
                privileges: [
                    'cms_page:delete',
                ],
                dependencies: [
                    'cms.viewer',
                ],
            },
        },
    });
