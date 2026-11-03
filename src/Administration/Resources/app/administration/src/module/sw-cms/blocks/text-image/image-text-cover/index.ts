import './component/register';
import './preview/register';

/**
 * @private
 * @sw-package discovery
 */
Shopware.Service('cmsService').registerCmsBlock({
    name: 'image-text-cover',
    label: 'sw-cms.blocks.textImage.imageTextCover.label',
    category: 'text-image',
    component: 'sw-cms-block-image-text-cover',
    previewComponent: 'sw-cms-preview-image-text-cover',
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
                        value: Shopware.Constants.CMS.MEDIA.previewMountain,
                        source: 'default',
                    },
                },
            },
        },
        right: 'text',
    },
});
