import CMS from '../../../constant/sw-cms.constant';

/**
 * @private
 * @sw-package discovery
 */
Shopware.Component.register('sw-cms-preview-image', () => import('./preview'));
/**
 * @private
 * @sw-package discovery
 */
Shopware.Component.register('sw-cms-block-image', () => import('./component'));

/**
 * @private
 * @sw-package discovery
 */
Shopware.Service('cmsService').registerCmsBlock({
    name: 'image',
    label: 'sw-cms.blocks.image.image.label',
    category: 'image',
    component: 'sw-cms-block-image',
    previewComponent: 'sw-cms-preview-image',
    defaultConfig: {
        marginBottom: '20px',
        marginTop: '20px',
        marginLeft: null,
        marginRight: null,
        sizingMode: 'boxed',
    },
    slots: {
        image: {
            type: 'image',
            default: {
                config: {
                    displayMode: { source: 'static', value: 'standard' },
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
