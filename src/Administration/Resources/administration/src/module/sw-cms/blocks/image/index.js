import { Application } from 'src/core/shopware';
import './component';
import './preview';

Application.getContainer('service').cmsService.registerCmsBlock({
    name: 'image',
    label: 'Image',
    category: 'standard',
    component: 'sw-cms-block-image',
    previewComponent: 'sw-cms-preview-image',
    defaultConfig: {
        marginBottom: null,
        marginTop: null,
        marginLeft: null,
        marginRight: null,
        sizingMode: 'full_width'
    },
    slots: {
        image: {
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
        }

    }
});
