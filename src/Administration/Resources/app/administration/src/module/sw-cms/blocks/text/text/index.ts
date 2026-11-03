import './component/register';
import './preview/register';

/**
 * @private
 * @sw-package discovery
 */
Shopware.Service('cmsService').registerCmsBlock({
    name: 'text',
    label: 'sw-cms.blocks.text.text.label',
    category: 'text',
    component: 'sw-cms-block-text',
    previewComponent: 'sw-cms-preview-text',
    defaultConfig: {
        marginBottom: '20px',
        marginTop: '20px',
        marginLeft: null,
        marginRight: null,
        sizingMode: 'boxed',
    },
    slots: {
        content: 'text',
    },
});
