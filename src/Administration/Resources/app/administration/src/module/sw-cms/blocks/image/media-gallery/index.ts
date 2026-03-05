/**
 * @private
 * @sw-package discovery
 */
Shopware.Component.register('sw-cms-preview-media-gallery', () => import('./preview'));
/**
 * @private
 * @sw-package discovery
 */
Shopware.Component.register('sw-cms-block-media-gallery', () => import('./component'));

/**
 * @private
 * @sw-package discovery
 */
Shopware.Service('cmsService').registerCmsBlock({
    name: 'media-gallery',
    label: 'sw-cms.blocks.image.mediaGallery.label',
    category: 'image',
    component: 'sw-cms-block-media-gallery',
    previewComponent: 'sw-cms-preview-media-gallery',
    defaultConfig: {
        marginBottom: '20px',
        marginTop: '20px',
        marginLeft: null,
        marginRight: null,
        sizingMode: 'boxed',
    },
    slots: {
        mediaGallery: {
            type: 'media-gallery',
            default: {
                config: {},
                data: {
                    sliderItems: {
                        source: 'default',
                        value: [
                            {
                                url: null,
                                newTab: false,
                                mediaId: null,
                                fileName: Shopware.Constants.CMS.MEDIA.previewMountain,
                                mediaUrl: null,
                            },
                            {
                                url: null,
                                newTab: false,
                                mediaId: null,
                                fileName: Shopware.Constants.CMS.MEDIA.previewGlasses,
                                mediaUrl: null,
                            },
                            {
                                url: null,
                                newTab: false,
                                mediaId: null,
                                fileName: Shopware.Constants.CMS.MEDIA.previewPlant,
                                mediaUrl: null,
                            },
                        ],
                    },
                },
            },
        },
    },
});
