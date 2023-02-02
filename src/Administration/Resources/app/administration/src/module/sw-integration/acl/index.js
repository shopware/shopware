Shopware.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'settings',
    key: 'integration',
    roles: {
        viewer: {
            privileges: [
                'integration:read',
                'acl_role:read',
            ],
            dependencies: [],
        },
        editor: {
            privileges: [
                'integration:update',
                'api_action_access-key_integration',
                'integration_role:create',
                'integration_role:delete',
            ],
            dependencies: [
                'integration.viewer',
            ],
        },
        creator: {
            privileges: [
                'integration:create',
            ],
            dependencies: [
                'integration.viewer',
                'integration.editor',
            ],
        },
        deleter: {
            privileges: [
                'integration:delete',
            ],
            dependencies: [
                'integration.viewer',
            ],
        },
    },
});
