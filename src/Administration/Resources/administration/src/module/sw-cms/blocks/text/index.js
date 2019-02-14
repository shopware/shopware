import cmsService from 'src/module/sw-cms/service/cms.service';
import './component';
import './preview';

cmsService.registerCmsBlock({
    name: 'text',
    label: 'Text',
    component: 'sw-cms-block-text',
    previewComponent: 'sw-cms-preview-text',
    slots: {
        'text-content': 'text'
    }
});
