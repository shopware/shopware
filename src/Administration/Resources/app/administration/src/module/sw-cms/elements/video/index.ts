import { VIDEO_DEFAULT_CONFIG } from '../image/config.constant';
import './component/register';
import './config/register';
import './preview/register';

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
