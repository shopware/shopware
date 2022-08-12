import './page/sw-generic-custom-entity-list';

const { Module } = Shopware;

Module.register('sw-custom-entity', {
    type: 'plugin',
    name: 'custom-entity',
    version: '1.0.0',
    targetVersion: '1.0.0',

    routes: {
        index: {
            component: 'sw-generic-custom-entity-list',
            path: ':entityName/list',
        },

        detail: {
            component: 'sw-generic-custom-entity-detail',
            path: ':entityName/detail/:id?',
        },
    },
});
