/**
 * @sw-package framework
 */

import { apiUsageMigration, componentMember, removedComponent, templateAttribute, upgradeGuide } from '../helpers';

export default apiUsageMigration({
    id: 'api.unused-order-components',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'These order create and state history components were unused and are removed without replacement.',
    references: [
        upgradeGuide('UPGRADE-6.8.md#removed-unused-order-components'),
    ],
    usage: [
        removedComponent({
            names: [
                'sw-order-create-base',
                'sw-order-create-details-header',
                'sw-order-create-details-body',
                'sw-order-create-details-footer',
                'sw-order-create-address-modal',
                'sw-order-state-history-card',
                'sw-order-state-history-card-entry',
                'sw-order-user-card',
            ],
            message:
                'Core no longer uses this order component. It is removed in 6.8 without replacement. Remove this override or usage.',
        }),
        componentMember({
            components: ['sw-order-state-history-modal'],
            names: ['total'],
            message: 'sw-order-state-history-modal no longer has total in 6.8. Use dataSource.length instead.',
        }),
        templateAttribute({
            elements: ['sw-order-state-history-modal'],
            names: ['is-loading'],
            message: 'sw-order-state-history-modal no longer has the isLoading prop in 6.8. Remove the binding.',
        }),
    ],
});
