/**
 * @private
 * @sw-package discovery
 */
Shopware.Component.register('sw-cms-block-age-verification', () => import('./component'));
/**
 * @private
 * @sw-package discovery
 */
Shopware.Component.register('sw-cms-preview-age-verification', () => import('./preview'));

/**
 * @private
 * @sw-package discovery
 */
Shopware.Service('cmsService').registerCmsBlock({
    name: 'age-verification',
    label: 'sw-cms.blocks.text.ageVerification.label',
    category: 'text',
    component: 'sw-cms-block-age-verification',
    previewComponent: 'sw-cms-preview-age-verification',
    defaultConfig: {
        marginBottom: '20px',
        marginTop: '20px',
        marginLeft: null,
        marginRight: null,
        sizingMode: 'boxed',
    },
    slots: {
        content: 'age-verification',
    },
});
