import { Application } from 'src/core/shopware';
import './component';
import './config';
import './preview';

Application.getContainer('service').cmsService.registerCmsElement({
    name: 'product-listing',
    label: 'Product listing',
    component: 'sw-cms-el-product-listing',
    previewComponent: 'sw-cms-el-preview-product-listing',
    configComponent: 'sw-cms-el-config-product-listing',
    defaultConfig: {
        products: {
            source: 'static',
            value: null
        },
        layout: {
            source: 'static',
            value: 'standard'
        }
    }
});
