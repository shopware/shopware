import { Application } from 'src/core/shopware';
import './component';
import './preview';

Application.getContainer('service').cmsService.registerCmsBlock({
    name: 'image-text',
    label: 'Image with text',
    category: 'standard',
    component: 'sw-cms-block-image-text',
    previewComponent: 'sw-cms-preview-image-text',
    slots: {
        left: 'image',
        right: 'text'
    }
});
