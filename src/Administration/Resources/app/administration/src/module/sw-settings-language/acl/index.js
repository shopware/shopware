/**
 * @package system-settings
 */
Shopware.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'settings',
    key: 'language',
    roles: {
        viewer: {
            privileges: [
                'language:read',
                'custom_field_set:read',
                'custom_field:read',
                'custom_field_set_relation:read',
            ],
            dependencies: [],
        },
        editor: {
            privileges: [
                'language:update',
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
            ],
            dependencies: [
                'language.viewer',
            ],
        },
    },
});
