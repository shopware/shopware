import { Application } from 'src/core/shopware';
import './component';
import './config';
import './preview';

Application.getContainer('service').cmsService.registerCmsElement({
    name: 'product-slider',
    label: 'Product Slider',
    component: 'sw-cms-el-product-slider',
    configComponent: 'sw-cms-el-config-product-slider',
    previewComponent: 'sw-cms-el-preview-product-slider',
    defaultConfig: {
        products: {
            source: 'static',
            value: []
        },
        title: {
            source: 'static',
            value: ''
        },
        displayMode: {
            source: 'static',
            value: 'standard'
        },
        boxLayout: {
            source: 'static',
            value: 'standard'
        },
        navigation: {
            source: 'static',
            value: true
        },
        rotate: {
            source: 'static',
            value: false
        },
        border: {
            source: 'static',
            value: false
        },
        elMinWidth: {
            source: 'static',
            value: '300px'
        },
        verticalAlign: {
            source: 'static',
            value: ''
        }
    }
});
