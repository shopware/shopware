import { Application } from 'src/core/shopware';
import './component';
import './config';
import './preview';

Application.getContainer('service').cmsService.registerCmsElement({
    name: 'form',
    label: 'Form',
    component: 'sw-cms-el-form',
    configComponent: 'sw-cms-el-config-form',
    previewComponent: 'sw-cms-el-preview-form',
    defaultConfig: {
        type: {
            source: 'static',
            value: 'contact'
        },
        title: {
            source: 'static',
            value: 'Form'
        },
        confirmationText: {
            source: 'static',
            value: 'This is the default confirmation text.'
        }
    }
});
