/**
 * @sw-package fundamentals@framework
 */
Shopware.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'settings',
    key: 'cookie_consent',
    roles: {
        // Read only on purpose: both tables are consent evidence and reject every write
        // from an API scope, so an editor or deleter role could never be satisfied.
        // The snapshots come with the log, a decision only names its cookie groups and
        // is not interpretable without the banner configuration it was given under.
        viewer: {
            privileges: [
                'cookie_consent_log:read',
                'cookie_consent_config_version:read',
            ],
            dependencies: [],
        },
    },
});
