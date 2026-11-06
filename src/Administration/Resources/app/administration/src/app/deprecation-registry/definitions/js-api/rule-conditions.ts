/**
 * @sw-package framework
 */

import { componentReference, jsApiMigration, upgradeGuide } from '../helpers';

export default jsApiMigration({
    id: 'js-api.rule-conditions',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description:
        'These rule condition components are removed. Core renders the conditions with sw-condition-generic-line-item.',
    references: [upgradeGuide('UPGRADE-6.8.md#removed-rule-condition-components')],
    usage: [
        componentReference({ component: 'sw-condition-line-item-in-category', to: 'sw-condition-generic' }),
        componentReference({ component: 'sw-condition-line-item-purchase-price', to: 'sw-condition-generic' }),
        componentReference({ component: 'sw-condition-is-net-select', to: 'sw-condition-generic' }),
    ],
});
