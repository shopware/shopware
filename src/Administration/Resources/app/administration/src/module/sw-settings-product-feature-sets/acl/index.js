Shopware.Service('privileges')
    .addPrivilegeMappingEntry({
        category: 'permissions',
        parent: 'settings',
        key: 'product_feature_sets',
        roles: {
            viewer: {
                privileges: [
                    'product_feature_set:read',
                    'custom_field:read',
                    'property_group:read',
                    'user_config:read',
                    'user_config:create',
                    'user_config:update',
                ],
                dependencies: [],
            },
            editor: {
                privileges: [
                    'product_feature_set:update',
                ],
                dependencies: [
                    'product_feature_sets.viewer',
                ],
            },
            creator: {
                privileges: [
                    'product_feature_set:create',
                ],
                dependencies: [
                    'product_feature_sets.viewer',
                    'product_feature_sets.editor',
                ],
            },
            deleter: {
                privileges: [
                    'product_feature_set:delete',
                ],
                dependencies: [
                    'product_feature_sets.viewer',
                ],
            },
        },
    });
