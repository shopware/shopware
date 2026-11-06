/**
 * @sw-package framework
 */

import { componentMembers, componentReference, jsApiMigration, upgradeGuide } from '../helpers';

export default jsApiMigration({
    id: 'js-api.unused-order-components',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'These order components were unused and are removed without replacement.',
    references: [upgradeGuide('UPGRADE-6.8.md#removed-unused-order-components')],
    usage: [
        componentReference({ component: 'sw-order-create-base' }),
        componentReference({ component: 'sw-order-create-details-header' }),
        componentReference({ component: 'sw-order-create-details-body' }),
        componentReference({ component: 'sw-order-create-details-footer' }),
        componentReference({ component: 'sw-order-create-address-modal' }),
        componentReference({ component: 'sw-order-state-history-card' }),
        componentReference({ component: 'sw-order-state-history-card-entry' }),
        componentReference({ component: 'sw-order-user-card' }),
        ...componentMembers({ components: ['sw-order-state-history-modal'], members: ['total'], to: 'dataSource.length' }),
    ],
});
