/**
 * @package system-settings
 */
Shopware.Service('privileges')
    .addPrivilegeMappingEntry({
        category: 'additional_permissions',
        parent: null,
        key: 'system',
        roles: {
            import_export: {
                privileges: [
                    'import_export_log:read',
                    'import_export_file:read',
                    'import_export_file:create',
                    'user:read',
                    'import_export_profile:read',
                    'import_export_profile:create',
                    'import_export_profile:delete',
                    'currency:read',
                ],
                dependencies: [],
            },
        },
    });
