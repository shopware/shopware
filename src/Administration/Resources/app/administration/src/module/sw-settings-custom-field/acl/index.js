Shopware.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'settings',
    key: 'custom_field',
    roles: {
        viewer: {
            privileges: [
                'custom_field_set:read',
                'custom_field_set_relation:read',
                'custom_field:read',
            ],
            dependencies: [],
        },
        editor: {
            privileges: [
                'custom_field_set:update',
                'custom_field:update',
                'custom_field:create',
                'custom_field:delete',
            ],
            dependencies: [
                'custom_field.viewer',
            ],
        },
        creator: {
            privileges: [
                'custom_field_set:create',
                'custom_field_set_relation:create',
            ],
            dependencies: [
                'custom_field.viewer',
                'custom_field.editor',
            ],
        },
        deleter: {
            privileges: [
                'custom_field_set:delete',
            ],
            dependencies: [
                'custom_field.viewer',
            ],
        },
    },
});
