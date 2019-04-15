import { Application } from 'src/core/shopware';
import './component';
import './config';
import './preview';

Application.getContainer('service').cmsService.registerCmsElement({
    name: 'product-box',
    label: 'Product box',
    component: 'sw-cms-el-product-box',
    previewComponent: 'sw-cms-el-preview-product-box',
    configComponent: 'sw-cms-el-config-product-box',
    defaultConfig: {
        product: {
            source: 'static',
            value: null
        },
        boxLayout: {
            source: 'static',
            value: 'standard'
        }
    }
});
