import { Application } from 'src/core/shopware';
import './component';
import './preview';

Application.getContainer('service').cmsService.registerCmsBlock({
    name: 'form',
    label: 'Form',
    category: 'form',
    component: 'sw-cms-block-form',
    previewComponent: 'sw-cms-preview-form',
    defaultConfig: {
        marginBottom: '20px',
        marginTop: '20px',
        marginLeft: '20px',
        marginRight: '20px',
        sizingMode: 'boxed'
    },
    slots: {
        form: {
            type: 'form',
            default: {
                config: {
                    content: { source: 'static', value: 'HTML-Code Formular' }
                    // type: { source: 'static', value: 'Formular-Typ' }
                }
            }
        }
        // content: 'form',
    }
});
