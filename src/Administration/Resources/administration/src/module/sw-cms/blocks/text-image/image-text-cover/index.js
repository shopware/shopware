import './component';
import './preview';

Shopware.Service.get('cmsService').registerCmsBlock({
    name: 'image-text-cover',
    label: 'Image next to text',
    category: 'text-image',
    component: 'sw-cms-block-image-text-cover',
    previewComponent: 'sw-cms-preview-image-text-cover',
    defaultConfig: {
        marginBottom: null,
        marginTop: null,
        marginLeft: null,
        marginRight: null,
        sizingMode: 'full_width'
    },
    slots: {
        left: {
            type: 'image',
            default: {
                config: {
                    displayMode: { source: 'static', value: 'cover' }
                },
                data: {
                    media: {
                        url: '/administration/static/img/cms/preview_mountain_large.jpg'
                    }
                }
            }
        },
        right: 'text'
    }
});
