import './component';
import './preview';

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
                        url: '/administration/static/img/cms/preview_mountain_large.jpg',
                    },
                },
            },
        },

    },
});
