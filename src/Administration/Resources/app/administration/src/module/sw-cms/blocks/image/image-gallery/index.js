import CMS from '../../../constant/sw-cms.constant';

import './component';
import './preview';

Shopware.Service('cmsService').registerCmsBlock({
    name: 'image-gallery',
    label: 'sw-cms.blocks.image.imageGallery.label',
    category: 'image',
    component: 'sw-cms-block-image-gallery',
    previewComponent: 'sw-cms-preview-image-gallery',
    defaultConfig: {
        marginBottom: '20px',
        marginTop: '20px',
        marginLeft: '20px',
        marginRight: '20px',
        sizingMode: 'boxed',
    },
    slots: {
        imageGallery: {
            type: 'image-gallery',
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
                            {
                                url: null,
                                newTab: false,
                                mediaId: null,
                                fileName: CMS.MEDIA.previewGlasses,
                                mediaUrl: null,
                            },
                            {
                                url: null,
                                newTab: false,
                                mediaId: null,
                                fileName: CMS.MEDIA.previewPlant,
                                mediaUrl: null,
                            },
                        ],
                    },
                },
            },
        },
    },
});
