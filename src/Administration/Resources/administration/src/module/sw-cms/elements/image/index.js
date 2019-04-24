import { Application } from 'src/core/shopware';
import './component';
import './config';
import './preview';

Application.getContainer('service').cmsService.registerCmsElement({
    name: 'image',
    label: 'Image',
    component: 'sw-cms-el-image',
    configComponent: 'sw-cms-el-config-image',
    previewComponent: 'sw-cms-el-preview-image',
    defaultConfig: {
        media: {
            source: 'static',
            value: null
        },
        displayMode: {
            source: 'static',
            value: 'standard'
        }
    }
});
