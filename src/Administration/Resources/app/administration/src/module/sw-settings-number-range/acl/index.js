Shopware.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'settings',
    key: 'number_ranges',
    roles: {
        viewer: {
            privileges: [
                'number_range:read',
                'number_range_type:read',
                'number_range_sales_channel:read',
                'number_range_state:read',
                'sales_channel:read'
            ],
            dependencies: []
        },
        editor: {
            privileges: [
                'number_range:update',
                'number_range_sales_channel:delete'
            ],
            dependencies: [
                'number_ranges.viewer'
            ]
        },
        creator: {
            privileges: [
                'number_range:create',
                'number_range_sales_channel:create'
            ],
            dependencies: [
                'number_ranges.viewer',
                'number_ranges.editor'
            ]
        },
        deleter: {
            privileges: [
                'number_range:delete'
            ],
            dependencies: [
                'number_ranges.viewer'
            ]
        }
    }
});
