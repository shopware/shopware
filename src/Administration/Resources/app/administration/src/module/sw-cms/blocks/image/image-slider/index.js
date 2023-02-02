import CMS from '../../../constant/sw-cms.constant';

import './component';
import './preview';

/**
 * @private since v6.5.0
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
        marginLeft: '20px',
        marginRight: '20px',
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
