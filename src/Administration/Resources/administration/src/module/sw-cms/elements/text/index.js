import cmsService from 'src/module/sw-cms/service/cms.service';
import './component';
import './config';
import './preview';

cmsService.registerCmsElement({
    name: 'text',
    label: 'Text',
    component: 'sw-cms-el-text',
    configComponent: 'sw-cms-el-config-text',
    previewComponent: 'sw-cms-el-preview-text'
});
