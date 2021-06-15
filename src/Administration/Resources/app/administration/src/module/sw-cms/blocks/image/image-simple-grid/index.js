import './component';
import './preview';

Shopware.Service('cmsService').registerCmsBlock({
    name: 'image-simple-grid',
    label: 'sw-cms.blocks.image.imageSimpleGrid.label',
    category: 'image',
    component: 'sw-cms-block-image-simple-grid',
    previewComponent: 'sw-cms-preview-image-simple-grid',
    defaultConfig: {
        marginBottom: '20px',
        marginTop: '20px',
        marginLeft: '20px',
        marginRight: '20px',
        sizingMode: 'boxed',
    },
    slots: {
        'left-top': {
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
        'left-bottom': {
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
        right: {
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
    },
});
