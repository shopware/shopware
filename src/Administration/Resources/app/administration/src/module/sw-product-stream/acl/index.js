Shopware.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'catalogues',
    key: 'product_stream',
    roles: {
        viewer: {
            privileges: [
                'product_stream:read',
                'product_stream_filter:read',
                'custom_field_set:read',
                'custom_field:read',
                'custom_field_set_relation:read',
                'sales_channel:read',
                'sales_channel_type:read',
                'product:read',
                'product_manufacturer:read',
                'property_group_option:read',
                'property_group:read',
                'currency:read',
                'user_config:read',
                'user_config:create',
                'user_config:update',
            ],
            dependencies: [],
        },
        editor: {
            privileges: [
                'product_stream:update',
                'product_stream_filter:update',
                'product_stream_filter:delete',
                'product_stream_filter:create',
            ],
            dependencies: ['product_stream.viewer'],
        },
        creator: {
            privileges: ['product_stream:create'],
            dependencies: ['product_stream.viewer', 'product_stream.editor'],
        },
        deleter: {
            privileges: [
                'product_stream:delete',
            ],
            dependencies: ['product_stream.viewer'],
        },
    },
});
