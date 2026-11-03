import { IMAGE_DEFAULT_CONFIG } from './config.constant';
import './component/register';
import './config/register';
import './preview/register';

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
