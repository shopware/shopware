/**
 * @sw-package framework
 */

import { componentMembers, jsApiMigration, serviceMethod, upgradeGuide } from '../helpers';

export default jsApiMigration({
    id: 'js-api.automatic-promotion-toggle',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Automatic promotions can no longer be switched off in an existing order.',
    references: [upgradeGuide('UPGRADE-6.8.md#removed-the-automatic-promotion-toggle-of-orders')],
    usage: [
        ...componentMembers({
            components: ['sw-order-promotion-field'],
            members: [
                'automaticPromotions',
                'hasAutomaticPromotions',
                'changesetGenerator',
                'hasOrderUnsavedChanges',
                'disabledAutoPromotions',
                'createdComponent',
                'emitLoadingChange',
                'handleUnsavedOrderChangesResponse',
                'deleteAutomaticPromotions',
                'toggleAutomaticPromotions',
                'getLineItemByPromotionCode',
            ],
            to: 'applyAutomaticPromotions()',
        }),
        serviceMethod({
            service: 'orderService',
            method: 'toggleAutomaticPromotions',
            to: 'orderService.applyAutomaticPromotions(orderId, versionId)',
        }),
    ],
});
