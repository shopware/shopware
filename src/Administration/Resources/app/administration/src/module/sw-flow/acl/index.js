Shopware.Service('privileges')
    .addPrivilegeMappingEntry({
        category: 'permissions',
        parent: 'settings',
        key: 'flow',
        roles: {
            viewer: {
                privileges: [
                    'flow:read',
                ],
                dependencies: [],
            },
            editor: {
                privileges: [
                    'flow:update',
                ],
                dependencies: [
                    'flow.viewer',
                ],
            },
            creator: {
                privileges: [
                    'flow:create',
                ],
                dependencies: [
                    'flow.viewer',
                    'flow.editor',
                ],
            },
            deleter: {
                privileges: [
                    'flow:delete',
                ],
                dependencies: [
                    'flow.viewer',
                ],
            },
        },
    });
