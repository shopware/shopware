import CMS from '../../../constant/sw-cms.constant';

/**
 * @private
 * @package content
 */
Shopware.Component.register('sw-cms-preview-image-highlight-row', () => import('./preview'));
/**
 * @private
 * @package content
 */
Shopware.Component.register('sw-cms-block-image-highlight-row', () => import('./component'));

/**
 * @private
 * @package content
 */
Shopware.Service('cmsService').registerCmsBlock({
    name: 'image-highlight-row',
    label: 'sw-cms.blocks.image.imageHighlightRow.label',
    category: 'image',
    component: 'sw-cms-block-image-highlight-row',
    previewComponent: 'sw-cms-preview-image-highlight-row',
    defaultConfig: {
        marginBottom: '40px',
        marginTop: '40px',
        marginLeft: '20px',
        marginRight: '20px',
        sizingMode: 'boxed',
        backgroundColor: '#e9e9e9',
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
