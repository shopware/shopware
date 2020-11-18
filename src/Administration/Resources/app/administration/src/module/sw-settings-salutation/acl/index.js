Shopware.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'settings',
    key: 'salutation',
    roles: {
        viewer: {
            privileges: [
                'salutation:read'
            ],
            dependencies: []
        },
        editor: {
            privileges: [
                'salutation:update'
            ],
            dependencies: [
                'salutation.viewer'
            ]
        },
        creator: {
            privileges: [
                'salutation:create'
            ],
            dependencies: [
                'salutation.viewer',
                'salutation.editor'
            ]
        },
        deleter: {
            privileges: [
                'salutation:delete'
            ],
            dependencies: [
                'salutation.viewer'
            ]
        }
    }
});
