/**
 * @sw-package framework
 */

import { componentMember, componentReference, injectKey, jsApiMigration, upgradeGuide } from '../helpers';

export default jsApiMigration({
    id: 'js-api.agentic-commerce-ui',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description:
        'The Agentic Commerce Administration UI moves to the SwagAgenticCommerce plugin. Install it and remove this override.',
    references: [upgradeGuide('UPGRADE-6.8.md#agentic-commerce-administration-ui-moved-to-swagagenticcommerce')],
    usage: [
        componentMember({ components: ['sw-sales-channel-detail'], member: 'agenticCommerceExportConfig' }),
        componentMember({ components: ['sw-sales-channel-detail'], member: 'isAgenticCommerce' }),
        componentMember({ components: ['sw-sales-channel-detail'], member: 'hasSwagAgenticCommercePlugin' }),
        componentMember({ components: ['sw-sales-channel-detail'], member: 'showAgenticCommerceDeprecationBanner' }),
        componentMember({ components: ['sw-sales-channel-detail'], member: 'defaultAgenticCommerceExportConfig' }),
        componentMember({ components: ['sw-sales-channel-detail'], member: 'onClickInstallAgenticCommercePlugin' }),
        componentMember({ components: ['sw-sales-channel-detail'], member: 'validateAgenticCommerceExportConfig' }),
        componentMember({ components: ['sw-sales-channel-detail'], member: 'loadAgenticCommerceExportConfig' }),
        componentMember({ components: ['sw-sales-channel-detail'], member: 'saveAgenticCommerceExportConfig' }),
        componentMember({ components: ['sw-sales-channel-detail-base'], member: 'isAgenticCommerce' }),
        componentMember({ components: ['sw-sales-channel-detail-base'], member: 'resolvedAgenticCommerceExportConfig' }),
        componentMember({ components: ['sw-sales-channel-detail-base'], member: 'getAgenticCommerceExportElementBind' }),
        componentMember({ components: ['sw-sales-channel-detail-base'], member: 'getAgenticCommerceExportCardTitle' }),
        componentMember({
            components: ['sw-sales-channel-detail-base'],
            member: 'getAgenticCommerceExportCardPositionIdentifier',
        }),
        componentMember({ components: ['sw-sales-channel-detail-base'], member: 'onAgenticCommerceExportFieldUpdate' }),
        componentMember({ components: ['sw-sales-channel-modal-grid'], member: 'isAgenticCommerceSalesChannelType' }),
        componentMember({ components: ['sw-sales-channel-modal-grid'], member: 'showAgenticCommerceType' }),
        componentMember({ components: ['sw-sales-channel-detail-product-comparison'], member: 'isAgenticCommerce' }),
        componentMember({ components: ['sw-sales-channel-create-base'], member: 'prefillAgenticCommerceDefaults' }),
        componentReference({ component: 'sw-agentic-commerce-tracking-config' }),
        injectKey({ from: 'swSalesChannelDetailGetAgenticCommerceExportConfig' }),
    ],
});
