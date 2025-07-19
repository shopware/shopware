/** @private */
Shopware.Component.register('sw-cms-el-preview-location-renderer', () => import('./preview/index'));
/** @private */
Shopware.Component.register('sw-cms-el-config-location-renderer', () => import('./config'));
/** @private */
Shopware.Component.register('sw-cms-el-location-renderer', () => import('./component'));

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
