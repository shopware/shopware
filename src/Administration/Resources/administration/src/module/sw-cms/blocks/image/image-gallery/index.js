import './component';
import './preview';

Shopware.Service.get('cmsService').registerCmsBlock({
    name: 'image-gallery',
    label: 'Image gallery',
    category: 'image',
    component: 'sw-cms-block-image-gallery',
    previewComponent: 'sw-cms-preview-image-gallery',
    defaultConfig: {
        marginBottom: '20px',
        marginTop: '20px',
        marginLeft: '20px',
        marginRight: '20px',
        sizingMode: 'boxed'
    },
    slots: {
        imageGallery: 'image-gallery'
    }
});
