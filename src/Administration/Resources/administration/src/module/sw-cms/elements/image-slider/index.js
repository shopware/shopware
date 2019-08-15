import './component';
import './config';
import './preview';

const { Application } = Shopware;

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
        navigationArrows: {
            source: 'static',
            value: 'outside'
        },
        navigationDots: {
            source: 'static',
            value: null
        },
        displayMode: {
            source: 'static',
            value: 'standard'
        },
        minHeight: {
            source: 'static',
            value: '300px'
        },
        verticalAlign: {
            source: 'static',
            value: ''
        }
    }
});
