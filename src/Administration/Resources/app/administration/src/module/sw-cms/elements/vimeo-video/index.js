/**
 * @private
 * @package content
 */
Shopware.Component.register('sw-cms-el-preview-vimeo-video', () => import('./preview'));
/**
 * @private
 * @package content
 */
Shopware.Component.register('sw-cms-el-config-vimeo-video', () => import('./config'));
/**
 * @private
 * @package content
 */
Shopware.Component.register('sw-cms-el-vimeo-video', () => import('./component'));

/**
 * @private
 * @package content
 */
Shopware.Service('cmsService').registerCmsElement({
    name: 'vimeo-video',
    label: 'sw-cms.elements.vimeoVideo.label',
    component: 'sw-cms-el-vimeo-video',
    configComponent: 'sw-cms-el-config-vimeo-video',
    previewComponent: 'sw-cms-el-preview-vimeo-video',
    defaultConfig: {
        videoID: {
            source: 'static',
            value: '',
            required: true,
        },
        autoplay: {
            source: 'static',
            value: false,
        },
        byLine: {
            source: 'static',
            value: false,
        },
        color: {
            source: 'static',
            value: '',
        },
        doNotTrack: {
            source: 'static',
            value: true,
        },
        loop: {
            source: 'static',
            value: false,
        },
        portrait: {
            source: 'static',
            value: true,
        },
        title: {
            source: 'static',
            value: true,
        },
        controls: {
            source: 'static',
            value: true,
        },
        needsConfirmation: {
            source: 'static',
            value: false,
        },
        previewMedia: {
            source: 'static',
            value: null,
            entity: {
                name: 'media',
            },
        },
    },
});
