Shopware.Service('privileges')
    .addPrivilegeMappingEntry({
        category: 'permissions',
        parent: 'catalogues',
        key: 'review',
        roles: {
            viewer: {
                privileges: [
                    'product_review:read',
                    'customer:read',
                    'product:read',
                    'sales_channel:read',
                    'user_config:read',
                    'user_config:create',
                    'user_config:update'
                ],
                dependencies: []
            },
            editor: {
                privileges: [
                    'product_review:update'
                ],
                dependencies: [
                    'review.viewer'
                ]
            },
            creator: {
                privileges: [
                    'product_review:create'
                ],
                dependencies: [
                    'review.viewer',
                    'review.editor'
                ]
            },
            deleter: {
                privileges: [
                    'product_review:delete'
                ],
                dependencies: [
                    'review.viewer'
                ]
            }
        }
    });
