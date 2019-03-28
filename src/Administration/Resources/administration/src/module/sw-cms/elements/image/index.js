import cmsService from 'src/module/sw-cms/service/cms.service';
import './component';
import './config';
import './preview';

cmsService.registerCmsElement({
    name: 'image',
    label: 'Image',
    component: 'sw-cms-el-image',
    configComponent: 'sw-cms-el-config-image',
    previewComponent: 'sw-cms-el-preview-image',
    defaultConfig: {
        media: {
            source: 'static',
            value: null
        }
    }
});
