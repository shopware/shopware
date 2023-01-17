/** istanbul ignore file */

/**
 * @private
 * @package content
 */
Shopware.Component.register('sw-generic-custom-entity-detail', () => import('./page/sw-generic-custom-entity-detail'));
/**
 * @private
 * @package content
 */
Shopware.Component.register('sw-generic-custom-entity-list', () => import('./page/sw-generic-custom-entity-list'));
/**
 * @private
 * @package content
 */
Shopware.Component.register('sw-custom-entity-input-field', () => import('./component/sw-custom-entity-input-field'));
/**
 * @private
 * @package content
 */
Shopware.Component.register('sw-generic-cms-page-assignment', () => import('./component/sw-generic-cms-page-assignment'));

/**
 * @private
 * @package content
 */
Shopware.Component.register('sw-generic-seo-general-card', () => import('./component/sw-generic-seo-general-card'));
/**
 * @private
 */
Shopware.Component.register('sw-generic-social-media-card', () => import('./component/sw-generic-social-media-card'));

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
 * @package content
 */
export {};
