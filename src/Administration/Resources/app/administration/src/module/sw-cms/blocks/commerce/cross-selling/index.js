import './component';
import './preview';

/**
 * @private since v6.5.0
 */
Shopware.Service('cmsService').registerCmsBlock({
    name: 'cross-selling',
    label: 'sw-cms.blocks.commerce.crossSelling.label',
    category: 'commerce',
    component: 'sw-cms-block-cross-selling',
    previewComponent: 'sw-cms-preview-cross-selling',
    defaultConfig: {
        marginBottom: '20px',
        marginTop: '20px',
        marginLeft: '20px',
        marginRight: '20px',
        sizingMode: 'boxed',
    },
    slots: {
        content: 'cross-selling',
    },
});
