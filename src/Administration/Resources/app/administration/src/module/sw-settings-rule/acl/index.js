Shopware.Service('privileges')
    .addPrivilegeMappingEntry({
        category: 'permissions',
        parent: 'settings',
        key: 'rule',
        roles: {
            viewer: {
                privileges: [
                    'rule:read',
                    'rule_condition:read',
                    'customer_group:read',
                    'sales_channel:read',
                    'tax:read',
                    'payment_method:read',
                    'shipping_method:read',
                    'shipping_method_price:read',
                    'category:read',
                    'product:read',
                    'product_manufacturer:read',
                    'product_price:read',
                    'property_group_option:read',
                    'country:read',
                    'tag:read',
                    'currency:read',
                    'custom_field:read',
                    'custom_field_set:read',
                    'custom_field_set_relation:read',
                    'promotion:read',
                    'promotion_discount:read',
                    'promotion_setgroup:read',
                    'event_action:read',
                ],
                dependencies: [],
            },
            editor: {
                privileges: [
                    'rule:update',
                    'rule_condition:create',
                    'rule_condition:update',
                    'rule_condition:delete',
                ],
                dependencies: [
                    'rule.viewer',
                ],
            },
            creator: {
                privileges: [
                    'rule:create',
                ],
                dependencies: [
                    'rule.viewer',
                    'rule.editor',
                ],
            },
            deleter: {
                privileges: [
                    'rule:delete',
                ],
                dependencies: [
                    'rule.viewer',
                ],
            },
        },
    });
