import './component';
import './preview';

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
                        url: '/administration/static/img/cms/preview_camera_large.jpg',
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
                        url: '/administration/static/img/cms/preview_plant_large.jpg',
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
                        url: '/administration/static/img/cms/preview_glasses_large.jpg',
                    },
                },
            },
        },
    },
});
