/**
 * @package checkout
 */

Shopware.Service('privileges')
    .addPrivilegeMappingEntry({
        category: 'permissions',
        parent: 'settings',
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
                    Shopware.Service('privileges').getPrivileges('media.viewer'),
                    'tax:read',
                    'custom_field_set:read',
                    'custom_field:read',
                    'custom_field_set_relation:read',
                    'rule_condition:read',
                ],
                dependencies: [],
            },
            editor: {
                privileges: [
                    'shipping_method:update',
                    'tag:create',
                    Shopware.Service('privileges').getPrivileges('media.creator'),
                    'shipping_method_price:create',
                    'shipping_method_price:update',
                    'shipping_method_price:delete',
                    'shipping_method_tag:create',
                ],
                dependencies: [
                    'shipping.viewer',
                ],
            },
            creator: {
                privileges: [
                    'shipping_method:create',
                ],
                dependencies: [
                    'shipping.viewer',
                    'shipping.editor',
                ],
            },
            deleter: {
                privileges: [
                    'shipping_method:delete',
                ],
                dependencies: [
                    'shipping.viewer',
                ],
            },
        },
    });
