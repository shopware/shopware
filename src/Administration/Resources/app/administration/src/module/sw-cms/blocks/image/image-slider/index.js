import './component';
import './preview';

Shopware.Service('cmsService').registerCmsBlock({
    name: 'image-slider',
    label: 'sw-cms.blocks.image.imageSlider.label',
    category: 'image',
    component: 'sw-cms-block-image-slider',
    previewComponent: 'sw-cms-preview-image-slider',
    defaultConfig: {
        marginBottom: '20px',
        marginTop: '20px',
        marginLeft: '20px',
        marginRight: '20px',
        sizingMode: 'boxed',
    },
    slots: {
        imageSlider: 'image-slider',
    },
});
