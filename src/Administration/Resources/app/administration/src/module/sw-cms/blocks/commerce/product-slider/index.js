import './component';
import './preview';

/**
 * @private since v6.5.0
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
