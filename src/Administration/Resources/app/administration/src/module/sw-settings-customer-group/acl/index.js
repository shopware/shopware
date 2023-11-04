/**
 * @package customer-order
 */

Shopware.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'settings',
    key: 'customer_groups',
    roles: {
        viewer: {
            privileges: [
                'customer_group:read',
                'sales_channel:read',
                'customer:read',
                'seo_url:read',
                'sales_channel_domain:read',
                'custom_field_set:read',
                'custom_field:read',
                'custom_field_set_relation:read',
            ],
            dependencies: [],
        },
        editor: {
            privileges: [
                'customer_group:update',
                'customer_group_registration_sales_channels:create',
                'customer_group_registration_sales_channels:delete',
            ],
            dependencies: [
                'customer_groups.viewer',
            ],
        },
        creator: {
            privileges: [
                'customer_group:create',
            ],
            dependencies: [
                'customer_groups.viewer',
                'customer_groups.editor',
            ],
        },
        deleter: {
            privileges: [
                'customer_group:delete',
                'seo_url:delete',
            ],
            dependencies: [
                'customer_groups.viewer',
            ],
        },
    },
});
