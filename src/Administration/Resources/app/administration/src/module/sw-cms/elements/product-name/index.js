Shopware.Component.extend('sw-cms-el-product-name', 'sw-cms-el-text', () => import('./component'));
Shopware.Component.extend('sw-cms-el-config-product-name', 'sw-cms-el-config-text', () => import('./config'));

/**
 * @private
 * @package content
 */
Shopware.Service('cmsService').registerCmsElement({
    name: 'product-name',
    label: 'sw-cms.elements.productHeading.name.label',
    component: 'sw-cms-el-product-name',
    configComponent: 'sw-cms-el-config-product-name',
    defaultConfig: {
        content: {
            source: 'static',
            value: '<h2>Lorem ipsum dolor sit amet.</h2>',
        },
        verticalAlign: {
            source: 'static',
            value: null,
        },
    },
});
