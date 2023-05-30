/**
 * @private
 * @package content
 */
Shopware.Component.register('sw-cms-preview-product-listing', () => import('./preview'));
/**
 * @private
 * @package content
 */
Shopware.Component.register('sw-cms-block-product-listing', () => import('./component'));

/**
 * @private
 * @package content
 */
Shopware.Service('cmsService').registerCmsBlock({
    name: 'product-listing',
    label: 'sw-cms.blocks.commerce.productListing.label',
    category: 'commerce',
    hidden: true,
    removable: false,
    component: 'sw-cms-block-product-listing',
    previewComponent: 'sw-cms-preview-product-listing',
    defaultConfig: {
        marginBottom: '20px',
        marginTop: '20px',
        marginLeft: '20px',
        marginRight: '20px',
        sizingMode: 'boxed',
    },
    slots: {
        content: 'product-listing',
    },
});
