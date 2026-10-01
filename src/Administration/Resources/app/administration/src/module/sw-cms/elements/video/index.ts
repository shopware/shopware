import { VIDEO_DEFAULT_CONFIG } from '../image/config.constant';

/**
 * @private
 * @sw-package discovery
 */
Shopware.Component.register('sw-cms-el-preview-video', () => import('./preview'));
/**
 * @private
 * @sw-package discovery
 */
Shopware.Component.register('sw-cms-el-config-video', () => import('./config'));
/**
 * @private
 * @sw-package discovery
 */
Shopware.Component.register('sw-cms-el-video', () => import('./component'));

/**
 * @private
 * @sw-package discovery
 */
Shopware.Service('cmsService').registerCmsElement({
    name: 'video',
    label: 'sw-cms.elements.video.label',
    component: 'sw-cms-el-video',
    configComponent: 'sw-cms-el-config-video',
    previewComponent: 'sw-cms-el-preview-video',
    defaultConfig: VIDEO_DEFAULT_CONFIG,
});
