import './component';
import './preview';

Shopware.Service('cmsService').registerCmsBlock({
    name: 'image-text',
    label: 'sw-cms.blocks.textImage.imageText.label',
    category: 'text-image',
    component: 'sw-cms-block-image-text',
    previewComponent: 'sw-cms-preview-image-text',
    defaultConfig: {
        marginBottom: '20px',
        marginTop: '20px',
        marginLeft: '20px',
        marginRight: '20px',
        sizingMode: 'boxed',
    },
    slots: {
        left: 'image',
        right: 'text',
    },
});
