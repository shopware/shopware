import { Application } from 'src/core/shopware';
import './component';
import './preview';

Application.getContainer('service').cmsService.registerCmsBlock({
    name: 'product-slider',
    label: 'Product slider',
    category: 'commerce',
    component: 'sw-cms-block-product-slider',
    previewComponent: 'sw-cms-preview-product-slider',
    defaultConfig: {
        marginBottom: '20px',
        marginTop: '20px',
        marginLeft: '20px',
        marginRight: '20px',
        sizingMode: 'boxed'
    },
    slots: {
        productSlider: 'product-slider'
    }
});
