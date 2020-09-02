Shopware.Service('privileges')
    .addPrivilegeMappingEntry({
        category: 'permissions',
        parent: null,
        key: 'theme',
        roles: {
            viewer: {
                privileges: [
                    'theme:read',
                    'media:read',
                    'sales_channel:read',
                    'media_default_folder:read',
                    'media_folder:read'
                ],
                dependencies: []
            },
            editor: {
                privileges: [
                    'theme:update',
                    'tag:read',
                    'product_media:read',
                    'product:read',
                    'category:read',
                    'product_manufacturer:read',
                    'mail_template_media:read',
                    'mail_template:read',
                    'document_base_config:read',
                    'user:read',
                    'payment_method:read',
                    'shipping_method:read',
                    'custom_field_set:read',
                    'custom_field:read',
                    'custom_field_set_relation:read'
                ],
                dependencies: [
                    'theme.viewer'
                ]
            },
            creator: {
                privileges: [
                    'theme:create'
                ],
                dependencies: [
                    'theme.viewer',
                    'theme.editor'
                ]
            },
            deleter: {
                privileges: [
                    'theme:delete'
                ],
                dependencies: [
                    'theme.viewer'
                ]
            }
        }
    });
