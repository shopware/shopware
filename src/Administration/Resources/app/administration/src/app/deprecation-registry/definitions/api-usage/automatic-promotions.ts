/**
 * @sw-package framework
 */

import { apiUsageMigration, componentMember, memberUsage, upgradeGuide } from '../helpers';

const MESSAGE =
    'Automatic promotions can no longer be switched off in an order in 6.8. ' +
    'Use applyAutomaticPromotions() of sw-order-promotion-field or orderService.applyAutomaticPromotions(orderId, versionId).';

export default apiUsageMigration({
    id: 'api.order-automatic-promotion-toggle',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'The toggle that switched automatic promotions off in an existing order is removed.',
    references: [
        upgradeGuide('UPGRADE-6.8.md#removed-the-automatic-promotion-toggle-of-orders'),
    ],
    usage: [
        componentMember({
            components: ['sw-order-promotion-field'],
            names: [
                'automaticPromotions',
                'hasAutomaticPromotions',
                'changesetGenerator',
                'hasOrderUnsavedChanges',
                'disabledAutoPromotions',
                'emitLoadingChange',
                'handleUnsavedOrderChangesResponse',
                'deleteAutomaticPromotions',
                'toggleAutomaticPromotions',
                'getLineItemByPromotionCode',
            ],
            message: MESSAGE,
        }),
        componentMember({
            components: ['sw-order-promotion-field'],
            names: ['createdComponent'],
            message:
                "sw-order-promotion-field no longer has createdComponent() in 6.8, nothing calls it and $super('createdComponent') fails. Move your code to created() of the override.",
        }),
        memberUsage({ service: 'orderService', properties: ['toggleAutomaticPromotions'], message: MESSAGE }),
    ],
});
