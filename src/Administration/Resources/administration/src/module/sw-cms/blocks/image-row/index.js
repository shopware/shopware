import { Application } from 'src/core/shopware';
import './component';
import './preview';

Application.getContainer('service').cmsService.registerCmsBlock({
    name: 'image-row',
    label: 'Image row',
    component: 'sw-cms-block-image-row',
    previewComponent: 'sw-cms-preview-image-row',
    slots: {
        left: 'image',
        center: 'image',
        right: 'image'
    }
});
