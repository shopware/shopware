import './component';
import './config';

/**
 * @private since v6.5.0
 */
Shopware.Service('cmsService').registerCmsElement({
    name: 'product-name',
    label: 'sw-cms.elements.productHeading.name.label',
    component: 'sw-cms-el-product-name',
    configComponent: 'sw-cms-el-config-product-name',
    defaultConfig: {
        content: {
            source: 'static',
            value: '<h2>Lorem ipsum dolor sit amet.</h2>'.trim(),
        },
        verticalAlign: {
            source: 'static',
            value: null,
        },
    },
});
