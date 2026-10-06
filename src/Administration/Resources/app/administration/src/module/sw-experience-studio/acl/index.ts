/**
 * @sw-package discovery
 */
Shopware.Service('privileges')
    .addPrivilegeMappingEntry({
        category: 'permissions',
        parent: 'content',
        key: 'experience_studio',
        roles: {
            viewer: {
                privileges: [
                    'content_layout:read',
                    'sales_channel:read',
                ],
                dependencies: [],
            },
            editor: {
                privileges: [
                    'content_layout:update',
                    'content_layout:translate',
                ],
                dependencies: [
                    'experience_studio.viewer',
                ],
            },
            creator: {
                privileges: [
                    'content_layout:create',
                ],
                dependencies: [
                    'experience_studio.viewer',
                    'experience_studio.editor',
                ],
            },
            deleter: {
                privileges: [
                    'content_layout:delete',
                ],
                dependencies: [
                    'experience_studio.viewer',
                ],
            },
        },
    })
    .addPrivilegeMappingEntry({
        category: 'additional_permissions',
        parent: null,
        key: 'experience_studio',
        roles: {
            translator: {
                privileges: ['content_layout:translate'],
                dependencies: ['experience_studio.viewer'],
            },
        },
    });
