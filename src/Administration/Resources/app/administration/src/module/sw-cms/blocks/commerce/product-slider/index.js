/**
 * @private
 * @package content
 */
Shopware.Component.register('sw-cms-preview-product-slider', () => import('./preview'));
/**
 * @private
 * @package content
 */
Shopware.Component.register('sw-cms-block-product-slider', () => import('./component'));

/**
 * @private
 * @package content
 */
Shopware.Service('cmsService').registerCmsBlock({
    name: 'product-slider',
    label: 'sw-cms.blocks.commerce.productSlider.label',
    category: 'commerce',
    component: 'sw-cms-block-product-slider',
    previewComponent: 'sw-cms-preview-product-slider',
    defaultConfig: {
        marginBottom: '20px',
        marginTop: '20px',
        marginLeft: '20px',
        marginRight: '20px',
        sizingMode: 'boxed',
    },
    slots: {
        productSlider: 'product-slider',
    },
});
