import { Application } from 'src/core/shopware';
import './component';
import './preview';

Application.getContainer('service').cmsService.registerCmsBlock({
    name: 'text-teaser',
    label: 'Text teaser',
    category: 'standard',
    component: 'sw-cms-block-text-teaser',
    previewComponent: 'sw-cms-preview-text-teaser',
    slots: {
        'text-content': {
            type: 'text',
            default: {
                config: {
                    content: {
                        source: 'static',
                        value: `
                        <h1 style="text-align: center;">Lorem Ipsum dolor sit amet</h1>
                        <p style="text-align: center;"><i>Lorem ipsum dolor sit amet, consetetur sadipscing elitr, 
                        sed diam nonumy eirmod tempor invidunt ut labore</i></p>
                        `.trim()
                    }
                }
            }
        }
    }
});
