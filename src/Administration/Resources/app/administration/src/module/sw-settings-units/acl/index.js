Shopware.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'settings',
    key: 'scale_unit',
    roles: {
        viewer: {
            privileges: [
                'unit:read',
            ],
            dependencies: [],
        },
        editor: {
            privileges: [
                'unit:update',
            ],
            dependencies: [
                'scale_unit.viewer',
            ],
        },
        creator: {
            privileges: [
                'unit:create',
            ],
            dependencies: [
                'scale_unit.viewer',
                'scale_unit.editor',
            ],
        },
        deleter: {
            privileges: [
                'unit:delete',
            ],
            dependencies: [
                'scale_unit.viewer',
            ],
        },
    },
});
