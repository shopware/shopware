import './component';
import './preview';

const { Application } = Shopware;

Application.getContainer('service').cmsService.registerCmsBlock({
    name: 'vimeo-video',
    label: 'vimeo Video',
    category: 'video',
    component: 'sw-cms-block-vimeo-video',
    previewComponent: 'sw-cms-preview-vimeo-video',
    defaultConfig: {
        marginBottom: '20px',
        marginTop: '20px',
        marginLeft: '20px',
        marginRight: '20px',
        sizingMode: 'boxed'
    },
    slots: {
        video: 'vimeo-video'
    }
});
