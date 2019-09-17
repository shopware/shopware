import './component';
import './preview';

Shopware.Service.get('cmsService').registerCmsBlock({
    name: 'product-three-column',
    label: 'Three column product grid',
    category: 'commerce',
    component: 'sw-cms-block-product-three-column',
    previewComponent: 'sw-cms-preview-product-three-column',
    defaultConfig: {
        marginBottom: '20px',
        marginTop: '20px',
        marginLeft: '20px',
        marginRight: '20px',
        sizingMode: 'boxed'
    },
    slots: {
        left: 'product-box',
        center: 'product-box',
        right: 'product-box'
    }
});
