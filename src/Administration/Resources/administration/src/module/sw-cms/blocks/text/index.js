import { Application } from 'src/core/shopware';
import './component';
import './preview';

Application.getContainer('service').cmsService.registerCmsBlock({
    name: 'text',
    label: 'Text',
    component: 'sw-cms-block-text',
    previewComponent: 'sw-cms-preview-text',
    slots: {
        'text-content': 'text'
    }
});
