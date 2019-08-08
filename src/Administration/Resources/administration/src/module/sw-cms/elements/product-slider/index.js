import './component';
import './config';
import './preview';

const { Application } = Shopware;
const Criteria = Shopware.Data.Criteria;
const criteria = new Criteria();
criteria.addAssociation('cover');

Application.getContainer('service').cmsService.registerCmsElement({
    name: 'product-slider',
    label: 'Product Slider',
    component: 'sw-cms-el-product-slider',
    configComponent: 'sw-cms-el-config-product-slider',
    previewComponent: 'sw-cms-el-preview-product-slider',
    defaultConfig: {
        products: {
            source: 'static',
            value: [],
            required: true,
            entity: {
                name: 'product',
                criteria: criteria
            }
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
            value: null
        }
    }
});
