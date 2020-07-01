Shopware.Service('privileges')
    .addPrivilegeMappingEntry({
        category: 'permissions',
        parent: null,
        key: 'product',
        roles: {
            viewer: {
                privileges: [
                    'product:read',
                    'product_media:read',
                    'product_manufacturer:read',
                    'currency:read',
                    'property_group_option:read',
                    'product_price:read',
                    'tag:read',
                    'seo_url:read',
                    'product_cross_selling:read',
                    'product_cross_selling_assigned_products:read',
                    'category:read',
                    'product_visibility:read',
                    'sales_channel:read',
                    'product_configurator_setting:read',
                    'unit:read',
                    'product_review:read',
                    'product_category:read',
                    'main_category:read',
                    'tax:read',
                    'custom_field_set:read',
                    'custom_field:read',
                    'custom_field_set_relation:read',
                    'media_folder:read',
                    'media:read',
                    'media_default_folder:read',
                    'sales_channel_type:read',
                    'rule:read',
                    'property_group:read',
                    'product_stream:read',
                    'product_property:read',
                    'delivery_time:read',
                    'mail_template_media:read',
                    'mail_template:read',
                    'document_base_config:read',
                    'user:read',
                    'product_stream_filter:read',
                    'payment_method:read',
                    'shipping_method:read',
                    'product_tag:read'
                ],
                dependencies: []
            },
            editor: {
                privileges: [
                    'product_media:create',
                    'product_media:delete',
                    'product_manufacturer:create',
                    'product_manufacturer:delete',
                    'product_price:create',
                    'product_price:delete',
                    'product_cross_selling:create',
                    'product_cross_selling:delete',
                    'product_cross_selling_assigned_products:create',
                    'product_cross_selling_assigned_products:delete',
                    'product_visibility:create',
                    'product_visibility:delete',
                    'product_configurator_setting:create',
                    'product_configurator_setting:update',
                    'product_configurator_setting:delete',
                    'product_review:create',
                    'product_review:delete',
                    'product_stream:create',
                    'product_stream:delete',
                    'product:update',
                    'product_property:create',
                    'product_property:delete',
                    'product_category:create',
                    'product_category:delete',
                    'media:create',
                    'product_media:create',
                    'product_media:delete',
                    'product_tag:create',
                    'product_tag:delete',
                    'tag:create',
                    'main_category:create'
                ],
                dependencies: [
                    'product.viewer'
                ]
            },
            creator: {
                privileges: [
                    'delivery_time:read',
                    'product:create',
                    'product_option:create',
                    'product_configurator_setting:create',
                    'media:create',
                    'product_media:create',
                    'product_visibility:create'
                ],
                dependencies: [
                    'product.viewer',
                    'product.editor'
                ]
            },
            deleter: {
                privileges: [
                    'product:delete'
                ],
                dependencies: [
                    'product.viewer'
                ]
            }
        }
    });
