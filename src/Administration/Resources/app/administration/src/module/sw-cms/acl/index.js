Shopware.Service('privileges')
    .addPrivilegeMappingEntry({
        category: 'permissions',
        parent: null,
        key: 'cms',
        roles: {
            viewer: {
                privileges: [
                    'cms_page:read',
                    'media:read',
                    'cms_section:read',
                    'category:read',
                    'media_default_folder:read',
                    'media_folder:read'
                ],
                dependencies: []
            },
            editor: {
                privileges: [
                    'cms_page:update'
                    // TODO: Add `Shopware.Service('privileges').getPrivileges('media.editor')` in NEXT-8922
                ],
                dependencies: [
                    'cms.viewer'
                ]
            },
            creator: {
                privileges: [
                    'cms_page:create'
                ],
                dependencies: [
                    'cms.viewer',
                    'cms.editor'
                ]
            },
            deleter: {
                privileges: [
                    'cms_page:delete'
                ],
                dependencies: [
                    'cms.viewer'
                ]
            }
        }
    });
