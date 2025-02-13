/**
 * @private
 * @sw-package discovery
 */
Shopware.Component.register('sw-cms-el-preview-product-slider', () => import('./preview'));
/**
 * @private
 * @sw-package discovery
 */
Shopware.Component.register('sw-cms-el-config-product-slider', () => import('./config'));
/**
 * @private
 * @sw-package discovery
 */
Shopware.Component.register('sw-cms-el-product-slider', () => import('./component'));

/**
 * @private
 * @sw-package discovery
 */
Shopware.Service('cmsService').registerCmsElement({
    name: 'product-slider',
    label: 'sw-cms.elements.productSlider.label',
    component: 'sw-cms-el-product-slider',
    configComponent: 'sw-cms-el-config-product-slider',
    previewComponent: 'sw-cms-el-preview-product-slider',
    defaultConfig: {
        products: {
            source: 'static',
            value: [],
            required: true,
            entity: {
                name: 'product',
                criteria: new Shopware.Data.Criteria(1, 25).addAssociation('cover'),
            },
        },
        title: {
            source: 'static',
            value: '',
        },
        displayMode: {
            source: 'static',
            value: 'standard',
        },
        boxLayout: {
            source: 'static',
            value: 'standard',
        },
        navigationArrows: {
            source: 'static',
            value: 'outside',
        },
        rotate: {
            source: 'static',
            value: false,
        },
        autoplayTimeout: {
            source: 'static',
            value: 5000,
        },
        speed: {
            source: 'static',
            value: 300,
        },
        border: {
            source: 'static',
            value: false,
        },
        elMinWidth: {
            source: 'static',
            value: '300px',
        },
        verticalAlign: {
            source: 'static',
            value: null,
        },
        productStreamSorting: {
            source: 'static',
            value: 'name:ASC',
        },
        productStreamLimit: {
            source: 'static',
            value: 10,
        },
    },
    collect: Shopware.Service('cmsService').getCollectFunction(),
});
