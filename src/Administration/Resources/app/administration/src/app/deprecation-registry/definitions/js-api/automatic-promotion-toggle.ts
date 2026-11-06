/**
 * @sw-package framework
 */

import { componentMember, jsApiMigration, serviceMethod, upgradeGuide } from '../helpers';

export default jsApiMigration({
    id: 'js-api.automatic-promotion-toggle',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Automatic promotions can no longer be switched off in an existing order.',
    references: [upgradeGuide('UPGRADE-6.8.md#removed-the-automatic-promotion-toggle-of-orders')],
    usage: [
        componentMember({
            components: ['sw-order-promotion-field'],
            member: 'automaticPromotions',
            to: 'applyAutomaticPromotions()',
        }),
        componentMember({
            components: ['sw-order-promotion-field'],
            member: 'hasAutomaticPromotions',
            to: 'applyAutomaticPromotions()',
        }),
        componentMember({
            components: ['sw-order-promotion-field'],
            member: 'changesetGenerator',
            to: 'applyAutomaticPromotions()',
        }),
        componentMember({
            components: ['sw-order-promotion-field'],
            member: 'hasOrderUnsavedChanges',
            to: 'applyAutomaticPromotions()',
        }),
        componentMember({
            components: ['sw-order-promotion-field'],
            member: 'disabledAutoPromotions',
            to: 'applyAutomaticPromotions()',
        }),
        componentMember({
            components: ['sw-order-promotion-field'],
            member: 'createdComponent',
            to: 'applyAutomaticPromotions()',
        }),
        componentMember({
            components: ['sw-order-promotion-field'],
            member: 'emitLoadingChange',
            to: 'applyAutomaticPromotions()',
        }),
        componentMember({
            components: ['sw-order-promotion-field'],
            member: 'handleUnsavedOrderChangesResponse',
            to: 'applyAutomaticPromotions()',
        }),
        componentMember({
            components: ['sw-order-promotion-field'],
            member: 'deleteAutomaticPromotions',
            to: 'applyAutomaticPromotions()',
        }),
        componentMember({
            components: ['sw-order-promotion-field'],
            member: 'toggleAutomaticPromotions',
            to: 'applyAutomaticPromotions()',
        }),
        componentMember({
            components: ['sw-order-promotion-field'],
            member: 'getLineItemByPromotionCode',
            to: 'applyAutomaticPromotions()',
        }),
        serviceMethod({
            service: 'orderService',
            method: 'toggleAutomaticPromotions',
            to: 'orderService.applyAutomaticPromotions(orderId, versionId)',
        }),
    ],
});
