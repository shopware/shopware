Shopware.Service('privileges')
    .addPrivilegeMappingEntry({
        category: 'permissions',
        parent: 'catalogues',
        key: 'property',
        roles: {
            viewer: {
                privileges: [
                    'property_group_option:read',
                    'property_group:read',
                    'media_default_folder:read',
                    'media_folder:read',
                    'media:read',
                    'user_config:read',
                    'user_config:create',
                    'user_config:update',
                    'custom_field_set:read',
                    'custom_field:read',
                    'custom_field_set_relation:read',
                ],
                dependencies: [],
            },
            editor: {
                privileges: [
                    'property_group_option:update',
                    'property_group_option:update',
                    'property_group_option:delete',
                    'property_group_option:create',
                    'property_group:update',
                    'media:create',
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
                ],
                dependencies: [
                    'property.viewer',
                ],
            },
            creator: {
                privileges: [
                    'property_group:create',
                ],
                dependencies: [
                    'property.viewer',
                    'property.editor',
                ],
            },
            deleter: {
                privileges: [
                    'property_group:delete',
                ],
                dependencies: [
                    'property.viewer',
                ],
            },
        },
    });
