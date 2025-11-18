import IMAGE_DEFAULT_CONFIG from '../image/config.constant';

Shopware.Component.extend('sw-cms-el-config-manufacturer-logo', 'sw-cms-el-config-image', () => import('./config'));
Shopware.Component.extend('sw-cms-el-manufacturer-logo', 'sw-cms-el-image', () => import('./component'));

/**
 * @private
 * @sw-package discovery
 */
Shopware.Service('cmsService').registerCmsElement({
    name: 'manufacturer-logo',
    label: 'sw-cms.elements.productHeading.logo.label',
    component: 'sw-cms-el-manufacturer-logo',
    configComponent: 'sw-cms-el-config-manufacturer-logo',
    defaultConfig: IMAGE_DEFAULT_CONFIG,
});
