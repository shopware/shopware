Shopware.Service('privileges')
    .addPrivilegeMappingEntry({
        category: 'permissions',
        parent: null,
        key: 'cms',
        roles: {
            viewer: {
                privileges: [
                    'cms_page:read',
                    'media:read',
                    'category:read',
                    'media_default_folder:read',
                    'media_folder:read',
                    'sales_channel:read',
                    'cms_section:read',
                    'cms_block:read',
                    'cms_slot:read'
                ],
                dependencies: []
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
                    // TODO: Add `Shopware.Service('privileges').getPrivileges('media.editor')` in NEXT-8922
                    'product:read',
                    'property_group_option:read',
                    'property_group:read',
                    'product_stream:read',
                    'product_media:read',
                    'currency:read',
                    'product_manufacturer:read'
                ],
                dependencies: [
                    'cms.viewer'
                ]
            },
            creator: {
                privileges: [
                    'cms_page:create'
                ],
                dependencies: [
                    'cms.viewer',
                    'cms.editor'
                ]
            },
            deleter: {
                privileges: [
                    'cms_page:delete'
                ],
                dependencies: [
                    'cms.viewer'
                ]
            }
        }
    });
