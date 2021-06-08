Shopware.Service('privileges')
    .addPrivilegeMappingEntry({
        category: 'permissions',
        parent: 'settings',
        key: 'flow',
        roles: {
            viewer: {
                privileges: [
                    'flow:read',
                    'flow_sequence:read',
                    'rule:read',
                ],
                dependencies: [],
            },
            editor: {
                privileges: [
                    'flow:update',
                    'flow_sequence:update',
                    'flow_sequence:create',
                    'flow_sequence:delete',
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
