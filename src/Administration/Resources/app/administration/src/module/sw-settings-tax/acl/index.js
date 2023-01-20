/**
 * @package customer-order
 */

Shopware.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'settings',
    key: 'tax',
    roles: {
        viewer: {
            privileges: [
                'system_config:read',
                'tax:read',
                'tax_provider:read',
                'tax_rule:read',
                'tax_rule_type:read',
                'country:read',
                'custom_field_set:read',
                'custom_field:read',
                'custom_field_set_relation:read',
            ],
            dependencies: [],
        },
        editor: {
            privileges: [
                'system_config:update',
                'tax:update',
                'tax_rule:read',
                'tax_rule:create',
                'tax_rule:update',
                'tax_rule:delete',
            ],
            dependencies: [
                'tax.viewer',
            ],
        },
        creator: {
            privileges: [
                'tax:create',
            ],
            dependencies: [
                'tax.viewer',
                'tax.editor',
            ],
        },
        deleter: {
            privileges: [
                'tax:delete',
            ],
            dependencies: [
                'tax.viewer',
            ],
        },
    },
});
