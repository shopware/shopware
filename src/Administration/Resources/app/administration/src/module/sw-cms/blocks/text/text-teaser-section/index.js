import './component';
import './preview';

Shopware.Service('cmsService').registerCmsBlock({
    name: 'text-teaser-section',
    label: 'sw-cms.blocks.text.textTeaserSection.label',
    category: 'text',
    component: 'sw-cms-block-text-teaser-section',
    previewComponent: 'sw-cms-preview-text-teaser-section',
    defaultConfig: {
        marginBottom: '20px',
        marginTop: '20px',
        marginLeft: '20px',
        marginRight: '20px',
        sizingMode: 'boxed',
    },
    slots: {
        left: {
            type: 'text',
            default: {
                config: {
                    content: {
                        source: 'static',
                        value: `
                        <h2>Lorem ipsum dolor</h2>
                        <p><i>Sit amet, consetetur sadipscing elitr</i></p>
                        `.trim(),
                    },
                },
            },
        },
        right: {
            type: 'text',
            default: {
                config: {
                    content: {
                        source: 'static',
                        value: `
                        <p>Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor 
                        invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et 
                        justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est 
                        Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam 
                        nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. 
                        At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, 
                        no sea takimata sanctus est Lorem ipsum dolor sit amet.</p>
                        `.trim(),
                    },
                },
            },
        },
    },
});
