import './component';
import './preview';

Shopware.Service('cmsService').registerCmsBlock({
    name: 'center-text',
    label: 'sw-cms.blocks.textImage.centerText.label',
    category: 'text-image',
    component: 'sw-cms-block-center-text',
    previewComponent: 'sw-cms-preview-center-text',
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
                },
                data: {
                    media: {
                        url: '/administration/static/img/cms/preview_camera_large.jpg',
                    },
                },
            },
        },
        center: {
            type: 'text',
            default: {
                config: {
                    content: {
                        source: 'static',
                        value: `
                        <h2 style="text-align: center;">Lorem Ipsum dolor sit amet</h2>
                        <p style="text-align: center;">Lorem ipsum dolor sit amet, consetetur sadipscing elitr, 
                        sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, 
                        sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. 
                        Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet.</p>
                        `.trim(),
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
