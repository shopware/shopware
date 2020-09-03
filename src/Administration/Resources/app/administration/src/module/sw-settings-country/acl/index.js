Shopware.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'settings',
    key: 'country',
    roles: {
        viewer: {
            privileges: [
                'country:read',
                'country_state:read'
            ],
            dependencies: []
        },
        editor: {
            privileges: [
                'country:update',
                'country_state:update'
            ],
            dependencies: [
                'country.viewer'
            ]
        },
        creator: {
            privileges: [
                'country:create',
                'country_state:create'
            ],
            dependencies: [
                'country.viewer',
                'country.editor'
            ]
        },
        deleter: {
            privileges: [
                'country:delete',
                'country_state:delete'
            ],
            dependencies: [
                'country.viewer'
            ]
        }
    }
});
