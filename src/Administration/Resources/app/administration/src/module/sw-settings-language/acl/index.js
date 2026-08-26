/**
 * @sw-package fundamentals@discovery
 */
Shopware.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'settings',
    key: 'language',
    roles: {
        viewer: {
            privileges: [
                'language:read',
                'sales_channel:read',
                'system:translation:read',
                'custom_field_set:read',
                'custom_field:read',
                'custom_field_set_relation:read',
            ],
            dependencies: [],
        },
        editor: {
            privileges: [
                'language:update',
                'system:translation:create',
            ],
            dependencies: [
                'language.viewer',
            ],
        },
        creator: {
            privileges: [
                'language:create',
            ],
            dependencies: [
                'language.viewer',
                'language.editor',
            ],
        },
        deleter: {
            privileges: [
                'language:delete',
                'system:translation:delete',
            ],
            dependencies: [
                'language.viewer',
            ],
        },
    },
});
