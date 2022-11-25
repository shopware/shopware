/**
 * @private
 * @package content
 */
Shopware.Component.register('sw-cms-preview-gallery-buybox', () => import('./preview'));
/**
 * @private
 * @package content
 */
Shopware.Component.register('sw-cms-block-gallery-buybox', () => import('./component'));

/**
 * @private
 * @package content
 */
Shopware.Service('cmsService').registerCmsBlock({
    name: 'gallery-buybox',
    label: 'sw-cms.blocks.commerce.galleryBuyBox.label',
    category: 'commerce',
    component: 'sw-cms-block-gallery-buybox',
    previewComponent: 'sw-cms-preview-gallery-buybox',
    defaultConfig: {
        marginBottom: '20px',
        marginTop: '20px',
        marginLeft: '20px',
        marginRight: '20px',
        sizingMode: 'boxed',
    },
    slots: {
        left: 'image-gallery',
        right: 'buy-box',
    },
});
