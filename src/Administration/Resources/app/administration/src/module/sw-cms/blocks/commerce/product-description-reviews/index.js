import './component';
import './preview';

Shopware.Service('cmsService').registerCmsBlock({
    name: 'product-description-reviews',
    label: 'sw-cms.blocks.commerce.productDescriptionReviews.label',
    category: 'commerce',
    flag: Shopware.Feature.isActive('FEATURE_NEXT_10078'),
    hidden: !Shopware.Feature.isActive('FEATURE_NEXT_10078'),
    component: 'sw-cms-block-product-description-reviews',
    previewComponent: 'sw-cms-preview-product-description-reviews',
    defaultConfig: {
        marginBottom: '20px',
        marginTop: '20px',
        marginLeft: '20px',
        marginRight: '20px',
        sizingMode: 'boxed'
    },
    slots: {
        content: 'product-description-reviews'
    }
});
