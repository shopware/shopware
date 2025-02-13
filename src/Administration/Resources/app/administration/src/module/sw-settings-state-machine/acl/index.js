/**
 * @sw-package checkout
 */
Shopware.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'settings',
    key: 'state_machine',
    roles: {
        viewer: {
            privileges: [
                'state_machine:read',
                'state_machine_state:read',
            ],
            dependencies: [],
        },
        editor: {
            privileges: [
                'state_machine:update',
                'state_machine_state:update',
            ],
            dependencies: [
                'state_machine.viewer',
            ],
        },
    },
});
