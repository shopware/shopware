import './component';
import './config';
import './preview';

Shopware.Service('cmsService').registerCmsElement({
    name: 'product-listing',
    label: 'sw-cms.elements.productListing.label',
    hidden: true,
    removable: false,
    component: 'sw-cms-el-product-listing',
    previewComponent: 'sw-cms-el-preview-product-listing',
    configComponent: 'sw-cms-el-config-product-listing',
    defaultConfig: {
        boxLayout: {
            source: 'static',
            value: 'standard'
        }
    }
});
