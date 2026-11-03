import './component/register';
import './config/register';
import './preview/register';

/**
 * @private
 * @sw-package discovery
 */
Shopware.Service('cmsService').registerCmsElement({
    name: 'buy-box',
    label: 'sw-cms.elements.buyBox.label',
    component: 'sw-cms-el-buy-box',
    configComponent: 'sw-cms-el-config-buy-box',
    previewComponent: 'sw-cms-el-preview-buy-box',
    disabledConfigInfoTextKey: 'sw-cms.elements.buyBox.infoText.tooltipSettingDisabled',
    defaultConfig: {
        product: {
            source: 'static',
            value: null,
            required: true,
            entity: {
                name: 'product',
                criteria: new Shopware.Data.Criteria(1, 25).addAssociation('deliveryTime'),
            },
        },
        alignment: {
            source: 'static',
            value: null,
        },
    },
    defaultData: {
        product: {
            name: 'Lorem Ipsum dolor',
            productNumber: 'XXXXXX',
            minPurchase: 1,
            deliveryTime: {
                name: '1-3 days',
            },
            price: [{ gross: 0.0 }],
        },
    },
    collect: Shopware.Service('cmsService').getCollectFunction(),
});
