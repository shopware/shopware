/**
 * @sw-package framework
 */
Shopware.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'settings',
    key: 'oauth_client',
    roles: {
        viewer: { privileges: ['oauth_client:read'], dependencies: [] },
        editor: { privileges: ['oauth_client:update'], dependencies: ['oauth_client.viewer'] },
        creator: { privileges: ['oauth_client:create'], dependencies: ['oauth_client.viewer'] },
        deleter: { privileges: ['oauth_client:delete'], dependencies: ['oauth_client.viewer'] },
    },
});
