import { Application } from 'src/core/shopware';
import './component';
import './preview';

Application.getContainer('service').cmsService.registerCmsBlock({
    name: 'image',
    label: 'Image',
    category: 'standard',
    component: 'sw-cms-block-image',
    previewComponent: 'sw-cms-preview-image',
    slots: {
        image: 'image'
    }
});
