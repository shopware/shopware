Shopware.Service('privileges')
    .addPrivilegeMappingEntry({
        category: 'additional_permissions',
        parent: null,
        key: 'orders',
        roles: {
            create_discounts: {
                privileges: ['order:create:discount'],
                dependencies: []
            }
        }
    });
