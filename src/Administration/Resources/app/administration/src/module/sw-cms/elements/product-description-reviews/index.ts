import './component/register';
import './config/register';
import './preview/register';

/**
 * @private
 * @sw-package discovery
 */
Shopware.Service('cmsService').registerCmsElement({
    name: 'product-description-reviews',
    label: 'sw-cms.elements.productDescriptionReviews.label',
    component: 'sw-cms-el-product-description-reviews',
    configComponent: 'sw-cms-el-config-product-description-reviews',
    previewComponent: 'sw-cms-el-preview-product-description-reviews',
    disabledConfigInfoTextKey: 'sw-cms.elements.productDescriptionReviews.infoText.descriptionAndReviewsElement',
    defaultConfig: {
        product: {
            source: 'static',
            value: null,
            required: true,
            entity: {
                name: 'product',
                criteria: new Shopware.Data.Criteria(1, 25).addAssociation('properties'),
            },
        },
        alignment: {
            source: 'static',
            value: null,
        },
    },
    collect: Shopware.Service('cmsService').getCollectFunction(),
});
