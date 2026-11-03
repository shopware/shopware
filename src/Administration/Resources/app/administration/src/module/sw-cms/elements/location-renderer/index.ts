import './component/register';
import './config/register';
/** @private */
Shopware.Component.register('sw-cms-el-preview-location-renderer', () => import('./preview/index'));

/**
 * @private
 * @sw-package discovery
 */
export interface ElementDataProp {
    name: string;
    label: string;
    component: string;
    previewComponent: string;
    configComponent: string;
    defaultConfig: {
        [key: string]: unknown;
    };
    appData: {
        baseUrl: string;
    };
}
