import CMS from '../../../constant/sw-cms.constant';
import './component';
import './preview';

Shopware.Service('cmsService').registerCmsBlock({
    name: 'image-three-cover',
    label: 'sw-cms.blocks.image.imageThreeCover.label',
    category: 'image',
    component: 'sw-cms-block-image-three-cover',
    previewComponent: 'sw-cms-preview-image-three-cover',
    defaultConfig: {
        marginBottom: null,
        marginTop: null,
        marginLeft: null,
        marginRight: null,
        sizingMode: 'full_width',
    },
    slots: {
        left: {
            type: 'image',
            default: {
                config: {
                    displayMode: { source: 'static', value: 'cover' },
                },
                data: {
                    media: {
                        value: CMS.MEDIA.previewCamera,
                        source: 'default',
                    },
                },
            },
        },
        center: {
            type: 'image',
            default: {
                config: {
                    displayMode: { source: 'static', value: 'cover' },
                },
                data: {
                    media: {
                        value: CMS.MEDIA.previewPlant,
                        source: 'default',
                    },
                },
            },
        },
        right: {
            type: 'image',
            default: {
                config: {
                    displayMode: { source: 'static', value: 'cover' },
                },
                data: {
                    media: {
                        value: CMS.MEDIA.previewGlasses,
                        source: 'default',
                    },
                },
            },
        },
    },
});
