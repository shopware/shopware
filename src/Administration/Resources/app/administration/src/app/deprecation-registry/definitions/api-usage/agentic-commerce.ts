/**
 * @sw-package framework
 */

import { apiUsageMigration, componentMember, injectKey, removedComponent, upgradeGuide } from '../helpers';

const MESSAGE =
    'The Agentic Commerce Administration UI moves to the SwagAgenticCommerce plugin in 6.8. Install it and remove this override.';

const MEMBERS: Record<string, string[]> = {
    'sw-sales-channel-detail': [
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
    'sw-sales-channel-detail-base': [
        'isAgenticCommerce',
        'resolvedAgenticCommerceExportConfig',
        'getAgenticCommerceExportElementBind',
        'getAgenticCommerceExportCardTitle',
        'getAgenticCommerceExportCardPositionIdentifier',
        'onAgenticCommerceExportFieldUpdate',
    ],
    'sw-sales-channel-modal-grid': [
        'isAgenticCommerceSalesChannelType',
        'showAgenticCommerceType',
    ],
    'sw-sales-channel-detail-product-comparison': ['isAgenticCommerce'],
    'sw-sales-channel-create-base': ['prefillAgenticCommerceDefaults'],
};

export default apiUsageMigration({
    id: 'api.agentic-commerce-ui',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'The Agentic Commerce Administration UI moved to the SwagAgenticCommerce plugin.',
    references: [
        upgradeGuide('UPGRADE-6.8.md#agentic-commerce-administration-ui-moved-to-swagagenticcommerce'),
    ],
    usage: [
        ...Object.entries(MEMBERS).map(([component, names]) => {
            return componentMember({ components: [component], names, message: MESSAGE });
        }),
        removedComponent({ names: ['sw-agentic-commerce-tracking-config'], message: MESSAGE }),
        injectKey({ names: ['swSalesChannelDetailGetAgenticCommerceExportConfig'], message: MESSAGE }),
    ],
});
