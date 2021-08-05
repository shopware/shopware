import './component';
import './preview';

Shopware.Service('cmsService').registerCmsBlock({
    name: 'image',
    label: 'sw-cms.blocks.image.image.label',
    category: 'image',
    component: 'sw-cms-block-image',
    previewComponent: 'sw-cms-preview-image',
    defaultConfig: {
        marginBottom: '20px',
        marginTop: '20px',
        marginLeft: '20px',
        marginRight: '20px',
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
                        url: '/administration/static/img/cms/preview_mountain_large.jpg',
                    },
                },
            },
        },

    },
});
