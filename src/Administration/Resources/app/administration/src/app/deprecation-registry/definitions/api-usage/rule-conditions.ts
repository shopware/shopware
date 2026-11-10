/**
 * @sw-package framework
 */

import { apiUsageMigration, removedComponent, stringLiteral, upgradeGuide } from '../helpers';

const RULE_CONDITIONS = [
    'sw-condition-line-item-in-category',
    'sw-condition-line-item-purchase-price',
    'sw-condition-is-net-select',
];

const MESSAGE =
    'This rule condition component is removed in 6.8. ' +
    'Use sw-condition-generic (sw-condition-generic-line-item for line items), it renders the condition from its backend config.';

export default apiUsageMigration({
    id: 'api.removed-rule-conditions',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Core renders these rule conditions with sw-condition-generic-line-item from their backend configuration.',
    references: [
        upgradeGuide('UPGRADE-6.8.md#removed-rule-condition-components'),
    ],
    usage: [
        removedComponent({ names: RULE_CONDITIONS, message: MESSAGE }),
        stringLiteral({ values: RULE_CONDITIONS, message: MESSAGE }),
    ],
});
