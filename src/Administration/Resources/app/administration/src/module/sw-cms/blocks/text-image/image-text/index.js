import CMS from '../../../constant/sw-cms.constant';
import './component';
import './preview';

/**
 * @private since v6.5.0
 */
Shopware.Service('cmsService').registerCmsBlock({
    name: 'image-text',
    label: 'sw-cms.blocks.textImage.imageText.label',
    category: 'text-image',
    component: 'sw-cms-block-image-text',
    previewComponent: 'sw-cms-preview-image-text',
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
        right: 'text',
    },
});
