Shopware.Service('privileges')
    .addPrivilegeMappingEntry({
        category: 'permissions',
        parent: 'settings',
        key: 'document',
        roles: {
            viewer: {
                privileges: [
                    'document_base_config:read',
                    'document_type:read',
                    'document_base_config_sales_channel:read',
                    'sales_channel:read',
                    'order:read',
                    'currency:read',
                    'custom_field_set:read',
                    'custom_field:read',
                    'custom_field_set_relation:read',
                ],
                dependencies: [],
            },
            editor: {
                privileges: [
                    'document_base_config:update',
                ],
                dependencies: [
                    'document.viewer',
                ],
            },
            creator: {
                privileges: [
                    'document_base_config:create',
                    'document_base_config_sales_channel:create',
                ],
                dependencies: [
                    'document.viewer',
                    'document.editor',
                ],
            },
            deleter: {
                privileges: [
                    'document_base_config:delete',
                ],
                dependencies: [
                    'document.viewer',
                ],
            },
        },
    });
