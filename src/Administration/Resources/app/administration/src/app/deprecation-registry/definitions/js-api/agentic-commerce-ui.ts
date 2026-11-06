/**
 * @sw-package framework
 */

import { componentMembers, componentReference, injectKey, jsApiMigration, upgradeGuide } from '../helpers';

export default jsApiMigration({
    id: 'js-api.agentic-commerce-ui',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description:
        'The Agentic Commerce Administration UI moves to the SwagAgenticCommerce plugin. Install it and remove this override.',
    references: [upgradeGuide('UPGRADE-6.8.md#agentic-commerce-administration-ui-moved-to-swagagenticcommerce')],
    usage: [
        ...componentMembers({
            components: ['sw-sales-channel-detail'],
            members: [
                'agenticCommerceExportConfig',
                'isAgenticCommerce',
                'hasSwagAgenticCommercePlugin',
                'showAgenticCommerceDeprecationBanner',
                'defaultAgenticCommerceExportConfig',
                'onClickInstallAgenticCommercePlugin',
                'validateAgenticCommerceExportConfig',
                'loadAgenticCommerceExportConfig',
                'saveAgenticCommerceExportConfig',
            ],
        }),
        ...componentMembers({
            components: ['sw-sales-channel-detail-base'],
            members: [
                'isAgenticCommerce',
                'resolvedAgenticCommerceExportConfig',
                'getAgenticCommerceExportElementBind',
                'getAgenticCommerceExportCardTitle',
                'getAgenticCommerceExportCardPositionIdentifier',
                'onAgenticCommerceExportFieldUpdate',
            ],
        }),
        ...componentMembers({
            components: ['sw-sales-channel-modal-grid'],
            members: ['isAgenticCommerceSalesChannelType', 'showAgenticCommerceType'],
        }),
        ...componentMembers({ components: ['sw-sales-channel-detail-product-comparison'], members: ['isAgenticCommerce'] }),
        ...componentMembers({ components: ['sw-sales-channel-create-base'], members: ['prefillAgenticCommerceDefaults'] }),
        componentReference({ component: 'sw-agentic-commerce-tracking-config' }),
        injectKey({ from: 'swSalesChannelDetailGetAgenticCommerceExportConfig' }),
    ],
});
