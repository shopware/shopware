import IMAGE_DEFAULT_CONFIG from './config.constant';

/**
 * @private
 * @sw-package discovery
 */
Shopware.Component.register('sw-cms-el-preview-image', () => import('./preview'));
/**
 * @private
 * @sw-package discovery
 */
Shopware.Component.register('sw-cms-el-config-image', () => import('./config'));
/**
 * @private
 * @sw-package discovery
 */
Shopware.Component.register('sw-cms-el-image', () => import('./component'));

/**
 * @private
 * @sw-package discovery
 */
Shopware.Service('cmsService').registerCmsElement({
    name: 'image',
    label: 'sw-cms.elements.image.label',
    component: 'sw-cms-el-image',
    configComponent: 'sw-cms-el-config-image',
    previewComponent: 'sw-cms-el-preview-image',
    defaultConfig: IMAGE_DEFAULT_CONFIG,
});
