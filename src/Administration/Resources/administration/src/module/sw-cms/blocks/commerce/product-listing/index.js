import './component';
import './preview';

Shopware.Service.get('cmsService').registerCmsBlock({
    name: 'product-listing',
    label: 'Product listing',
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
        sizingMode: 'boxed'
    },
    slots: {
        content: 'product-listing'
    }
});
