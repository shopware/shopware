/**
 * @deprecated tag:v6.5.0 - Will be removed in v6.5.0. Please use `sw-flow` - Flow builder instead.
 */
Shopware.Service('privileges')
    .addPrivilegeMappingEntry({
        category: 'permissions',
        parent: 'settings',
        key: 'event_action',
        roles: {
            viewer: {
                privileges: [
                    'event_action:read',
                    'sales_channel:read',
                    'rule:read',
                    'mail_template:read',
                    'mail_template_type:read',
                    'custom_field_set:read',
                    'custom_field:read',
                    'custom_field_set_relation:read',
                ],
                dependencies: [],
            },
            editor: {
                privileges: [
                    'event_action:update',
                    'event_action_rule:delete',
                    'event_action_sales_channel:delete',
                    'event_action_sales_channel:create',
                    'event_action_rule:create',
                ],
                dependencies: [
                    'customer.viewer',
                ],
            },
            creator: {
                privileges: [
                    'event_action:create',
                ],
                dependencies: [
                    'customer.viewer',
                    'customer.editor',
                ],
            },
            deleter: {
                privileges: [
                    'event_action:delete',
                ],
                dependencies: [
                    'customer.viewer',
                ],
            },
        },
    });
