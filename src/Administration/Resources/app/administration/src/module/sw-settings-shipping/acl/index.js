Shopware.Service('privileges')
    .addPrivilegeMappingEntry({
        category: 'permissions',
        parent: null,
        key: 'shipping',
        roles: {
            viewer: {
                privileges: [
                    'shipping_method:read',
                    'shipping_method_price:read',
                    'rule:read',
                    'tag:read',
                    'currency:read',
                    'delivery_time:read',
                    'media_folder:read',
                    'media_default_folder:read',
                    'media:read'
                ],
                dependencies: []
            },
            editor: {
                privileges: [
                    'shipping_method:update',
                    'tag:create',
                    'shipping_method_price:create',
                    'shipping_method_price:update',
                    'shipping_method_price:delete',
                    'shipping_method_tag:create'
                ],
                dependencies: [
                    'shipping.viewer'
                ]
            },
            creator: {
                privileges: [
                    'shipping_method:create'
                ],
                dependencies: [
                    'shipping.viewer',
                    'shipping.editor'
                ]
            },
            deleter: {
                privileges: [
                    'shipping_method:delete'
                ],
                dependencies: [
                    'shipping.viewer'
                ]
            }
        }
    });
