import cmsService from 'src/module/sw-cms/service/cms.service';
import './component';
import './preview';

cmsService.registerCmsBlock({
    name: 'image',
    label: 'Image',
    component: 'sw-cms-block-image',
    previewComponent: 'sw-cms-preview-image',
    slots: {
        image: 'image'
    }
});
