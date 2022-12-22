/**
 * @package system-settings
 */
Shopware.Service('privileges')
    .addPrivilegeMappingEntry({
        category: 'additional_permissions',
        parent: null,
        key: 'system',
        roles: {
            clear_cache: {
                privileges: [
                    'system:clear:cache',
                    'system:cache:info',
                    'api_action_cache_index',
                ],
                dependencies: [],
            },
        },
    });
