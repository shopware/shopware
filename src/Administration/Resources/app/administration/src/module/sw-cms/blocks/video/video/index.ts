/**
 * @private
 * @sw-package discovery
 */
Shopware.Component.register('sw-cms-preview-video', () => import('./preview'));
/**
 * @private
 * @sw-package discovery
 */
Shopware.Component.register('sw-cms-block-video', () => import('./component'));

/**
 * @private
 * @sw-package discovery
 */
Shopware.Service('cmsService').registerCmsBlock({
    name: 'video',
    label: 'sw-cms.blocks.video.video.label',
    category: 'video',
    component: 'sw-cms-block-video',
    previewComponent: 'sw-cms-preview-video',
    defaultConfig: {
        marginBottom: '20px',
        marginTop: '20px',
        marginLeft: null,
        marginRight: null,
        sizingMode: 'boxed',
    },
    slots: {
        video: {
            type: 'video',
            default: {
                config: {
                    displayMode: { source: 'static', value: 'standard' },
                },
            },
        },
    },
});
