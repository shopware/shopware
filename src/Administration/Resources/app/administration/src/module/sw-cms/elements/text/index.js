import './component';
import './config';
import './preview';

Shopware.Service('cmsService').registerCmsElement({
    name: 'text',
    label: 'sw-cms.elements.text.label',
    component: 'sw-cms-el-text',
    configComponent: 'sw-cms-el-config-text',
    previewComponent: 'sw-cms-el-preview-text',
    defaultConfig: {
        content: {
            source: 'static',
            value: `
                <h2>Lorem Ipsum dolor sit amet</h2>
                <p>Lorem ipsum dolor sit amet, consetetur sadipscing elitr, 
                sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, 
                sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. 
                Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. 
                Lorem ipsum dolor sit amet, consetetur sadipscing elitr, 
                sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. 
                At vero eos et accusam et justo duo dolores et ea rebum. 
                Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet.</p>
            `.trim(),
        },
        verticalAlign: {
            source: 'static',
            value: null,
        },
    },
});
