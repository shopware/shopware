/**
 * @private
 * @sw-package discovery
 */
Shopware.Component.register('sw-cms-preview-youtube-video', () => import('./preview'));
/**
 * @private
 * @sw-package discovery
 */
Shopware.Component.register('sw-cms-block-youtube-video', () => import('./component'));

/**
 * @private
 * @sw-package discovery
 */
Shopware.Service('cmsService').registerCmsBlock({
    name: 'youtube-video',
    label: 'sw-cms.blocks.video.youtubeVideo.label',
    category: 'video',
    component: 'sw-cms-block-youtube-video',
    previewComponent: 'sw-cms-preview-youtube-video',
    defaultConfig: {
        marginBottom: '20px',
        marginTop: '20px',
        marginLeft: null,
        marginRight: null,
        sizingMode: 'boxed',
    },
    slots: {
        video: 'youtube-video',
    },
});
