import './component';
import './preview';

Shopware.Service('cmsService').registerCmsBlock({
    name: 'text',
    label: 'sw-cms.blocks.text.text.label',
    category: 'text',
    component: 'sw-cms-block-text',
    previewComponent: 'sw-cms-preview-text',
    defaultConfig: {
        marginBottom: '20px',
        marginTop: '20px',
        marginLeft: '20px',
        marginRight: '20px',
        sizingMode: 'boxed',
    },
    slots: {
        content: 'text',
    },
});
