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
                'ucp_sales_channel_config:read',
                'ucp_signing_key:read',
                'ucp_platform_profile_cache:read',
                'ucp_negotiation_session:read',
            ],
            dependencies: [],
        },
        editor: {
            privileges: [
                'ucp_sales_channel_config:create',
                'ucp_sales_channel_config:update',
                'ucp_platform_profile_cache:delete',
            ],
            dependencies: [
                'ucp.viewer',
            ],
        },
        key_rotator: {
            privileges: [
                'ucp_signing_key:create',
                'ucp_signing_key:update',
                'ucp_signing_key:delete',
            ],
            dependencies: [
                'ucp.editor',
            ],
        },
    },
});
