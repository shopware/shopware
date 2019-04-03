import cmsService from 'src/module/sw-cms/service/cms.service';
import './component';
import './preview';

cmsService.registerCmsBlock({
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
