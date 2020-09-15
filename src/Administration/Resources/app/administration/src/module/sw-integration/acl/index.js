Shopware.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'settings',
    key: 'integration',
    roles: {
        viewer: {
            privileges: [
                'integration:read'
            ],
            dependencies: []
        },
        editor: {
            privileges: [
                'integration:update'
            ],
            dependencies: [
                'integration.viewer'
            ]
        },
        creator: {
            privileges: [
                'integration:create'
            ],
            dependencies: [
                'integration.viewer',
                'integration.editor'
            ]
        },
        deleter: {
            privileges: [
                'integration:delete'
            ],
            dependencies: [
                'integration.viewer'
            ]
        }
    }
});
