import './component/sw-custom-entity-input-field/register';
import './component/sw-generic-cms-page-assignment/register';
import './component/sw-generic-seo-general-card/register';
import './component/sw-generic-social-media-card/register';
import './page/sw-generic-custom-entity-detail/register';
import './page/sw-generic-custom-entity-list/register';

/**
 * @private
 */
Shopware.Module.register('sw-custom-entity', {
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

/**
 * @private
 * @sw-package framework
 */
export {};
