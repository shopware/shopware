import CMS from '../../../constant/sw-cms.constant';

/**
 * @private
 * @package content
 */
Shopware.Component.register('sw-cms-preview-image-cover', () => import('./preview'));
/**
 * @private
 * @package content
 */
Shopware.Component.register('sw-cms-block-image-cover', () => import('./component'));

/**
 * @private
 * @package content
 */
Shopware.Service('cmsService').registerCmsBlock({
    name: 'image-cover',
    label: 'sw-cms.blocks.image.imageCover.label',
    category: 'image',
    component: 'sw-cms-block-image-cover',
    previewComponent: 'sw-cms-preview-image-cover',
    defaultConfig: {
        marginBottom: null,
        marginTop: null,
        marginLeft: null,
        marginRight: null,
        sizingMode: 'full_width',
    },
    slots: {
        image: {
            type: 'image',
            default: {
                config: {
                    displayMode: { source: 'static', value: 'cover' },
                },
                data: {
                    media: {
                        value: CMS.MEDIA.previewMountain,
                        source: 'default',
                    },
                },
            },
        },

    },
});
