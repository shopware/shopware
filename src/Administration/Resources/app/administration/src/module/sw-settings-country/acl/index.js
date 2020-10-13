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
                'country_state:read',
                'country_state:create',
                'country_state:update',
                'country_state:delete'
            ],
            dependencies: [
                'country.viewer'
            ]
        },
        creator: {
            privileges: [
                'country:create'
            ],
            dependencies: [
                'country.viewer',
                'country.editor'
            ]
        },
        deleter: {
            privileges: [
                'country:delete'
            ],
            dependencies: [
                'country.viewer'
            ]
        }
    }
});
