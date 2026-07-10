/**
 * @private
 * @sw-package discovery
 */
Shopware.Component.register('sw-cms-el-age-verification', () => import('./component'));
/**
 * @private
 * @sw-package discovery
 */
Shopware.Component.register('sw-cms-el-preview-age-verification', () => import('./preview'));
/**
 * @private
 * @sw-package discovery
 */
Shopware.Component.register('sw-cms-el-config-age-verification', () => import('./config'));

/**
 * @private
 * @sw-package discovery
 */
Shopware.Service('cmsService').registerCmsElement({
    name: 'age-verification',
    label: 'sw-cms.elements.ageVerification.label',
    component: 'sw-cms-el-age-verification',
    configComponent: 'sw-cms-el-config-age-verification',
    previewComponent: 'sw-cms-el-preview-age-verification',
    defaultConfig: {
        minimumAge: {
            source: 'static',
            value: 18,
        },
        title: {
            source: 'static',
            value: '',
        },
        content: {
            source: 'static',
            value: '',
        },
        confirmButtonText: {
            source: 'static',
            value: '',
        },
        declineButtonText: {
            source: 'static',
            value: '',
        },
        declineUrl: {
            source: 'static',
            value: '',
        },
        cookieLifetime: {
            source: 'static',
            value: 30,
        },
    },
});
