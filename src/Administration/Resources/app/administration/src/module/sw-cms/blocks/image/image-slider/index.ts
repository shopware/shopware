import CMS from '../../../constant/sw-cms.constant';

/**
 * @private
 * @sw-package discovery
 */
Shopware.Component.register('sw-cms-preview-image-slider', () => import('./preview'));
/**
 * @private
 * @sw-package discovery
 */
Shopware.Component.register('sw-cms-block-image-slider', () => import('./component'));

/**
 * @private
 * @sw-package discovery
 */
Shopware.Service('cmsService').registerCmsBlock({
    name: 'image-slider',
    label: 'sw-cms.blocks.image.imageSlider.label',
    category: 'image',
    component: 'sw-cms-block-image-slider',
    previewComponent: 'sw-cms-preview-image-slider',
    defaultConfig: {
        marginBottom: '20px',
        marginTop: '20px',
        marginLeft: null,
        marginRight: null,
        sizingMode: 'boxed',
    },
    slots: {
        imageSlider: {
            type: 'image-slider',
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
                                fileName: CMS.MEDIA.previewMountain,
                                mediaUrl: null,
                            },
                        ],
                    },
                },
            },
        },
    },
});
