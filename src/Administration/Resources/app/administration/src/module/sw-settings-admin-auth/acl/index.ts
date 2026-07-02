/**
 * @sw-package framework
 */

if (Shopware.Feature.isActive('ADMIN_AUTH')) {
    Shopware.Service('privileges').addPrivilegeMappingEntry({
        category: 'permissions',
        parent: 'settings',
        key: 'admin_auth',
        roles: {
            viewer: {
                privileges: [
                    'admin_auth_provider:read',
                    'acl_role:read',
                    'system_config:read',
                ],
                dependencies: [],
            },
            editor: {
                privileges: [
                    'admin_auth_provider:update',
                    'system_config:update',
                ],
                dependencies: [
                    'admin_auth.viewer',
                ],
            },
            creator: {
                privileges: [
                    'admin_auth_provider:create',
                ],
                dependencies: [
                    'admin_auth.viewer',
                    'admin_auth.editor',
                ],
            },
            deleter: {
                privileges: [
                    'admin_auth_provider:delete',
                ],
                dependencies: [
                    'admin_auth.viewer',
                ],
            },
        },
    });
}

/**
 * @private
 * @sw-package framework
 */
export {};
