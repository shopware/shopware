import cmsService from 'src/module/sw-cms/service/cms.service';
import './component';
import './config';
import './preview';

cmsService.registerCmsElement({
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
