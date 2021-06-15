Shopware.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'settings',
    key: 'snippet',
    roles: {
        viewer: {
            privileges: [
                'snippet_set:read',
            ],
            dependencies: [],
        },
        editor: {
            privileges: [
                'snippet_set:update',
                'snippet:create',
                'snippet:update',
            ],
            dependencies: ['snippet.viewer'],
        },
        creator: {
            privileges: [
                'snippet_set:create',
                'snippet_set:update',
            ],
            dependencies: [
                'snippet.viewer',
                'snippet.editor',
            ],
        },
        deleter: {
            privileges: [
                'snippet_set:delete',
                'snippet:delete',
            ],
            dependencies: [
                'snippet.viewer',
                'snippet.editor',
            ],
        },
    },
});
