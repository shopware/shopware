Shopware.Component.extend('sw-cms-el-category-name', 'sw-cms-el-text', () => import('./component'));
Shopware.Component.extend('sw-cms-el-config-category-name', 'sw-cms-el-config-text', () => import('./config'));

/**
 * @private
 * @sw-package discovery
 */
Shopware.Service('cmsService').registerCmsElement({
    name: 'category-name',
    label: 'sw-cms.elements.categoryName.label',
    component: 'sw-cms-el-category-name',
    configComponent: 'sw-cms-el-config-category-name',
    defaultConfig: {
        content: {
            source: 'static',
            value: '<h1>Lorem ipsum dolor sit amet.</h1>',
        },
        verticalAlign: {
            source: 'static',
            value: null,
        },
    },
});
