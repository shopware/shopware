/**
 * @private
 * @package content
 */
Shopware.Component.register('sw-cms-el-preview-image', () => import('./preview'));
/**
 * @private
 * @package content
 */
Shopware.Component.register('sw-cms-el-config-image', () => import('./config'));
/**
 * @private
 * @package content
 */
Shopware.Component.register('sw-cms-el-image', () => import('./component'));

/**
 * @private
 * @package content
 */
Shopware.Service('cmsService').registerCmsElement({
    name: 'image',
    label: 'sw-cms.elements.image.label',
    component: 'sw-cms-el-image',
    configComponent: 'sw-cms-el-config-image',
    previewComponent: 'sw-cms-el-preview-image',
    defaultConfig: {
        media: {
            source: 'static',
            value: null,
            required: true,
            entity: {
                name: 'media',
            },
        },
        displayMode: {
            source: 'static',
            value: 'standard',
        },
        url: {
            source: 'static',
            value: null,
        },
        newTab: {
            source: 'static',
            value: false,
        },
        minHeight: {
            source: 'static',
            value: '340px',
        },
        verticalAlign: {
            source: 'static',
            value: null,
        },
    },
});
