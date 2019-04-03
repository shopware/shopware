import cmsService from 'src/module/sw-cms/service/cms.service';
import './component';
import './config';
import './preview';

cmsService.registerCmsElement({
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
