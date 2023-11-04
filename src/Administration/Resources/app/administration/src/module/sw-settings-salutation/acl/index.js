Shopware.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'settings',
    key: 'salutation',
    roles: {
        viewer: {
            privileges: [
                'salutation:read',
                'custom_field_set:read',
                'custom_field:read',
                'custom_field_set_relation:read',
            ],
            dependencies: [],
        },
        editor: {
            privileges: [
                'salutation:update',
            ],
            dependencies: [
                'salutation.viewer',
            ],
        },
        creator: {
            privileges: [
                'salutation:create',
            ],
            dependencies: [
                'salutation.viewer',
                'salutation.editor',
            ],
        },
        deleter: {
            privileges: [
                'salutation:delete',
            ],
            dependencies: [
                'salutation.viewer',
            ],
        },
    },
});
