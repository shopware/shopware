import cmsService from 'src/module/sw-cms/service/cms.service';
import './component';
import './preview';

cmsService.registerCmsBlock({
    name: 'image-text',
    label: 'Image with text',
    component: 'sw-cms-block-image-text',
    previewComponent: 'sw-cms-preview-image-text',
    slots: {
        left: 'image',
        right: 'text'
    }
});
