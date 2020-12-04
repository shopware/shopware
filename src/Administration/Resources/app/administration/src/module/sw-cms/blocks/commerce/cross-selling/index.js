import './component';
import './preview';

Shopware.Service('cmsService').registerCmsBlock({
    name: 'cross-selling',
    label: 'sw-cms.blocks.commerce.crossSelling.label',
    category: 'commerce',
    flag: Shopware.Feature.isActive('FEATURE_NEXT_10078'),
    hidden: !Shopware.Feature.isActive('FEATURE_NEXT_10078'),
    component: 'sw-cms-block-cross-selling',
    previewComponent: 'sw-cms-preview-cross-selling',
    defaultConfig: {
        marginBottom: '20px',
        marginTop: '20px',
        marginLeft: '20px',
        marginRight: '20px',
        sizingMode: 'boxed'
    },
    slots: {
        content: 'cross-selling'
    }
});
