import { Application } from 'src/core/shopware';
import './component';
import './config';
import './preview';

Application.getContainer('service').cmsService.registerCmsElement({
    name: 'image-slider',
    label: 'Image Slider',
    component: 'sw-cms-el-image-slider',
    configComponent: 'sw-cms-el-config-image-slider',
    previewComponent: 'sw-cms-el-preview-image-slider',
    defaultConfig: {
        sliderItems: {
            source: 'static',
            value: []
        },
        navigation: {
            source: 'static',
            value: {
                arrows: 'outside',
                dots: null
            }
        },
        displayMode: {
            source: 'static',
            value: 'standard'
        },
        minHeight: {
            source: 'static',
            value: '340px'
        }
    }
});
