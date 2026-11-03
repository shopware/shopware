import './component/register';
import './preview/register';

/**
 * @private
 * @sw-package discovery
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
        marginLeft: null,
        marginRight: null,
        sizingMode: 'boxed',
    },
    slots: {
        left: 'image-gallery',
        right: 'buy-box',
    },
});
