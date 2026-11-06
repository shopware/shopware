/**
 * @sw-package framework
 */

import { componentMigration, removeProp, upgradeGuide } from '../helpers';

export default componentMigration({
    id: 'component.sw-order-state-select-v2.style-props',
    component: 'sw-order-state-select-v2',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description:
        'sw-order-state-select-v2 derives its status color from state-name instead of background-style and rounded-style.',
    references: [
        upgradeGuide('UPGRADE-6.8.md#deprecated-state-select-styling-members-in-the-order-module'),
    ],
    onEveryUsage: [
        removeProp({
            prop: 'background-style',
            reviewWhenTrue:
                'Pass the technical name of the current state instead, e.g. :state-name="order.stateMachineState.technicalName".',
        }),
        removeProp({ prop: 'rounded-style' }),
    ],
});
