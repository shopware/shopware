import './component';
import './config';
import './preview';

const { Application } = Shopware;

Application.getContainer('service').cmsService.registerCmsElement({
    name: 'image-gallery',
    label: 'Image gallery',
    component: 'sw-cms-el-image-gallery',
    configComponent: 'sw-cms-el-config-image-gallery',
    previewComponent: 'sw-cms-el-preview-image-gallery',

    defaultConfig: {
        sliderItems: {
            source: 'static',
            value: [],
            required: true
        },
        navigationArrows: {
            source: 'static',
            value: 'inside'
        },
        navigationDots: {
            source: 'static',
            value: null
        },
        galleryPosition: {
            source: 'static',
            value: 'left'
        },
        displayMode: {
            source: 'static',
            value: 'standard'
        },
        minHeight: {
            source: 'static',
            value: '340px'
        },
        verticalAlign: {
            source: 'static',
            value: null
        },
        zoom: {
            source: 'static',
            value: false
        },
        fullScreen: {
            source: 'static',
            value: false
        }
    }
});
