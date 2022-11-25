/**
 * @private
 * @package content
 */
Shopware.Component.register('sw-cms-preview-product-description-reviews', () => import('./preview'));
/**
 * @private
 * @package content
 */
Shopware.Component.register('sw-cms-block-product-description-reviews', () => import('./component'));

/**
 * @private
 * @package content
 */
Shopware.Service('cmsService').registerCmsBlock({
    name: 'product-description-reviews',
    label: 'sw-cms.blocks.commerce.productDescriptionReviews.label',
    category: 'commerce',
    component: 'sw-cms-block-product-description-reviews',
    previewComponent: 'sw-cms-preview-product-description-reviews',
    defaultConfig: {
        marginBottom: '20px',
        marginTop: '20px',
        marginLeft: '20px',
        marginRight: '20px',
        sizingMode: 'boxed',
    },
    slots: {
        content: 'product-description-reviews',
    },
});
