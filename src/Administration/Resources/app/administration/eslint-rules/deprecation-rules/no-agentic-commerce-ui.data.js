/**
 * @sw-package framework
 *
 * The Agentic Commerce Administration UI, which 6.8 removes from the core and SwagAgenticCommerce provides instead.
 * Taken from the `@deprecated tag:v6.8.0` markers of the sales channel module, plus the integration view and its
 * route, which SwagAgenticCommerce registers when the core does not. The agentic files views
 * (`sw-sales-channel-detail-agentic-files`, `sw-sales-channel-detail-agentic-file`) stay in the core.
 */

const removed = (name) => `${name} is removed from the core, because the Agentic Commerce Administration UI moved to SwagAgenticCommerce. Install it and remove this code.`;

const IS_AGENTIC_COMMERCE_COMPONENTS = [
    'sw-sales-channel-detail',
    'sw-sales-channel-detail-base',
    'sw-sales-channel-create-base',
    'sw-sales-channel-detail-product-comparison',
];

module.exports = {
    id: 'agentic-commerce-ui',
    description: 'Marks the Agentic Commerce Administration UI, which 6.8 moves to SwagAgenticCommerce',
    components: {
        'sw-agentic-commerce-tracking-config': removed('sw-agentic-commerce-tracking-config'),
        'sw-sales-channel-detail-agentic-commerce-integration': removed('sw-sales-channel-detail-agentic-commerce-integration'),
    },
    members: [
        { name: 'swSalesChannelDetailGetAgenticCommerceExportConfig', unique: true, message: removed('The swSalesChannelDetailGetAgenticCommerceExportConfig provide') },
        { name: 'agenticCommerceExportConfig', unique: true, message: removed('agenticCommerceExportConfig') },
        { name: 'hasSwagAgenticCommercePlugin', unique: true, message: removed('sw-sales-channel-detail.hasSwagAgenticCommercePlugin') },
        { name: 'showAgenticCommerceDeprecationBanner', unique: true, message: removed('sw-sales-channel-detail.showAgenticCommerceDeprecationBanner') },
        { name: 'defaultAgenticCommerceExportConfig', unique: true, message: removed('sw-sales-channel-detail.defaultAgenticCommerceExportConfig') },
        { name: 'onClickInstallAgenticCommercePlugin', unique: true, message: removed('sw-sales-channel-detail.onClickInstallAgenticCommercePlugin()') },
        { name: 'validateAgenticCommerceExportConfig', unique: true, message: removed('sw-sales-channel-detail.validateAgenticCommerceExportConfig()') },
        { name: 'loadAgenticCommerceExportConfig', unique: true, message: removed('sw-sales-channel-detail.loadAgenticCommerceExportConfig()') },
        { name: 'saveAgenticCommerceExportConfig', unique: true, message: removed('sw-sales-channel-detail.saveAgenticCommerceExportConfig()') },
        { name: 'resolvedAgenticCommerceExportConfig', unique: true, message: removed('sw-sales-channel-detail-base.resolvedAgenticCommerceExportConfig') },
        { name: 'getAgenticCommerceExportElementBind', unique: true, message: removed('sw-sales-channel-detail-base.getAgenticCommerceExportElementBind()') },
        { name: 'getAgenticCommerceExportCardTitle', unique: true, message: removed('sw-sales-channel-detail-base.getAgenticCommerceExportCardTitle()') },
        { name: 'getAgenticCommerceExportCardPositionIdentifier', unique: true, message: removed('sw-sales-channel-detail-base.getAgenticCommerceExportCardPositionIdentifier()') },
        { name: 'onAgenticCommerceExportFieldUpdate', unique: true, message: removed('sw-sales-channel-detail-base.onAgenticCommerceExportFieldUpdate()') },
        { name: 'isAgenticCommerceSalesChannelType', unique: true, message: removed('sw-sales-channel-modal-grid.isAgenticCommerceSalesChannelType()') },
        { name: 'showAgenticCommerceType', unique: true, message: removed('sw-sales-channel-modal-grid.showAgenticCommerceType()') },
        { name: 'prefillAgenticCommerceDefaults', unique: true, message: removed('sw-sales-channel-create-base.prefillAgenticCommerceDefaults()') },
        { component: IS_AGENTIC_COMMERCE_COMPONENTS, name: 'isAgenticCommerce', message: removed('isAgenticCommerce') },
    ],
    globals: [
        { path: 'Shopware.Defaults.agenticCommerceTypeId', message: "Shopware.Defaults.agenticCommerceTypeId belongs to the Agentic Commerce Administration UI, which moved to SwagAgenticCommerce; SwagAgenticCommerce defines it when the core does not. Install it, or use the sales channel type id '5e29f9890c4d4d519a1c7f9d5c24b7c1'." },
    ],
    literals: [
        { value: 'sw.sales.channel.detail.agenticCommerceIntegration', message: removed('The route sw.sales.channel.detail.agenticCommerceIntegration') },
    ],
};
