import './component';
import './preview';

Shopware.Service('cmsService').registerCmsBlock({
    name: 'product-heading',
    label: 'sw-cms.blocks.commerce.productHeading.label',
    category: 'commerce',
    component: 'sw-cms-block-product-heading',
    previewComponent: 'sw-cms-preview-product-heading',
    defaultConfig: {
        marginTop: '20px',
        marginLeft: '20px',
        marginBottom: '20px',
        marginRight: '20px',
        sizingMode: 'boxed',
    },
    slots: {
        left: 'product-name',
        right: 'manufacturer-logo',
    },
});
