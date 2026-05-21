/**
 * @sw-package fundamentals@framework
 *
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 */
Shopware.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'settings',
    key: 'ucp',
    roles: {
        viewer: {
            privileges: [
                'sales_channel:read',
                'sales_channel_domain:read',
            ],
            dependencies: [],
        },
        editor: {
            privileges: [],
            dependencies: [
                'ucp.viewer',
            ],
        },
        key_rotator: {
            privileges: [],
            dependencies: [
                'ucp.editor',
            ],
        },
    },
});
