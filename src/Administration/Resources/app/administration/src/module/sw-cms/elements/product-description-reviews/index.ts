/**
 * @private
 * @package buyers-experience
 */
Shopware.Component.register('sw-cms-el-preview-product-description-reviews', () => import('./preview'));
/**
 * @private
 * @package buyers-experience
 */
Shopware.Component.register('sw-cms-el-config-product-description-reviews', () => import('./config'));
/**
 * @private
 * @package buyers-experience
 */
Shopware.Component.register('sw-cms-el-product-description-reviews', () => import('./component'));

/**
 * @private
 * @package buyers-experience
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
