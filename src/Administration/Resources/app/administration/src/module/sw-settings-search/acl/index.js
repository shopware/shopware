/**
 * @package system-settings
 */
Shopware.Service('privileges')
    .addPrivilegeMappingEntry({
        category: 'permissions',
        parent: 'settings',
        key: 'product_search_config',
        roles: {
            viewer: {
                privileges: [
                    'product_search_config:read',
                    'product_search_config_field:read',
                    'custom_field_set:read',
                    'product_search_keyword:read',
                    'product:read',
                    'sales_channel:read',
                    'custom_field:read',
                    'system:clear:cache',
                ],
                dependencies: [],
            },
            editor: {
                privileges: [
                    'product_search_config:update',
                    'product_search_config_field:update',
                    'product_search_keyword:update',
                    'system:clear:cache',
                ],
                dependencies: [
                    'product_search_config.viewer',
                ],
            },
            creator: {
                privileges: [
                    'product_search_config:create',
                    'product_search_config_field:create',
                    'product_search_keyword:create',
                    'system:clear:cache',
                ],
                dependencies: [
                    'product_search_config.viewer',
                    'product_search_config.editor',
                ],
            },
            deleter: {
                privileges: [
                    'product_search_config:delete',
                    'product_search_config_field:delete',
                    'product_search_keyword:delete',
                    'product_search_config:update',
                    'system:clear:cache',
                ],
                dependencies: [
                    'product_search_config.viewer',
                ],
            },
        },
    });
