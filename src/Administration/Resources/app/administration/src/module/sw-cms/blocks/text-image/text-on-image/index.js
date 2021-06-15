import './component';
import './preview';

Shopware.Service('cmsService').registerCmsBlock({
    name: 'text-on-image',
    label: 'sw-cms.blocks.textImage.textOnImage.label',
    category: 'text-image',
    component: 'sw-cms-block-text-on-image',
    previewComponent: 'sw-cms-preview-text-on-image',
    defaultConfig: {
        marginBottom: '20px',
        marginTop: '20px',
        marginLeft: '20px',
        marginRight: '20px',
        sizingMode: 'boxed',
        backgroundMedia: {
            url: '/administration/static/img/cms/preview_mountain_large.jpg',
        },
    },
    slots: {
        content: {
            type: 'text',
            default: {
                config: {
                    content: {
                        source: 'static',
                        value: `
                        <h2 style="text-align: center; color: #FFFFFF">Lorem Ipsum</h2>
                        <p style="text-align: center; color: #FFFFFF">Lorem ipsum dolor sit amet, consetetur
                        sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam
                        lorem ipsum dolor sit amet.</p>
                        `.trim(),
                    },
                },
            },
        },
    },
});
