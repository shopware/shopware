import CMS from '../../../constant/sw-cms.constant';

/**
 * @private
 * @package content
 */
Shopware.Component.register('sw-cms-preview-image-bubble-row', () => import('./preview'));
/**
 * @private
 * @package content
 */
Shopware.Component.register('sw-cms-block-image-bubble-row', () => import('./component'));

/**
 * @private
 * @package content
 */
Shopware.Service('cmsService').registerCmsBlock({
    name: 'image-bubble-row',
    label: 'sw-cms.blocks.image.imageBubbleRow.label',
    category: 'image',
    component: 'sw-cms-block-image-bubble-row',
    previewComponent: 'sw-cms-preview-image-bubble-row',
    defaultConfig: {
        marginBottom: '20px',
        marginTop: '20px',
        marginLeft: '20px',
        marginRight: '20px',
        sizingMode: 'boxed',
    },
    slots: {
        left: {
            type: 'image',
            default: {
                config: {
                    displayMode: { source: 'static', value: 'cover' },
                    minHeight: { source: 'static', value: '300px' },
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
                    minHeight: { source: 'static', value: '300px' },
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
                    minHeight: { source: 'static', value: '300px' },
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
