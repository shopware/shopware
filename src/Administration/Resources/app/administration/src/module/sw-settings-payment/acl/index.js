Shopware.Service('privileges')
    .addPrivilegeMappingEntry({
        category: 'permissions',
        parent: null,
        key: 'payment',
        roles: {
            viewer: {
                privileges: [
                    'payment_method:read',
                    'media_folder:read',
                    'media:read',
                    'media_default_folder:read',
                    'rule:read'
                ],
                dependencies: []
            },
            editor: {
                privileges: [
                    'payment_method:update',
                    'media:create'
                ],
                dependencies: [
                    'payment.viewer'
                ]
            },
            creator: {
                privileges: [
                    'payment_method:create'
                ],
                dependencies: [
                    'payment.viewer',
                    'payment.editor'
                ]
            },
            deleter: {
                privileges: [
                    'payment_method:delete'
                ],
                dependencies: [
                    'payment.viewer'
                ]
            }
        }
    });
