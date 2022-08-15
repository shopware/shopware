import './page/sw-generic-custom-entity-list';
import './page/sw-generic-custom-entity-detail';
import './component/sw-custom-entity-input-field';

const { Module } = Shopware;

/**
 * @private
 */
Module.register('sw-custom-entity', {
    title: 'sw-custom-entity.general.mainMenuItemGeneral',
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
            meta: {
                parentPath: 'sw.custom.entity.index',
            },
        },

        create: {
            component: 'sw-generic-custom-entity-detail',
            path: ':entityName/create',
            meta: {
                parentPath: 'sw.custom.entity.index',
            },
        },
    },
});
