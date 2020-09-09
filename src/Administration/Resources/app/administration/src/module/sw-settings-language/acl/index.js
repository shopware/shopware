Shopware.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'settings',
    key: 'language',
    roles: {
        viewer: {
            privileges: [
                'language:read'
            ],
            dependencies: []
        },
        editor: {
            privileges: [
                'language:update'
            ],
            dependencies: [
                'language.viewer'
            ]
        },
        creator: {
            privileges: [
                'language:create'
            ],
            dependencies: [
                'language.viewer',
                'language.editor'
            ]
        },
        deleter: {
            privileges: [
                'language:delete'
            ],
            dependencies: [
                'language.viewer'
            ]
        }
    }
});
