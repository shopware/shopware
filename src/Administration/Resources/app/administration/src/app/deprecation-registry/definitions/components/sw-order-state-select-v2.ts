/**
 * @sw-package framework
 */

import { backgroundStyleToStateName, componentMigration, removeProp, replaceProp, upgradeGuide } from '../helpers';

export default componentMigration({
    id: 'component.sw-order-state-select-v2',
    component: 'sw-order-state-select-v2',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    onlyWhenSelected: true,
    description:
        'The rounded-style and background-style props of sw-order-state-select-v2 have no effect anymore. The select takes its color from state-name, which exists since 6.7.16.0.',
    references: [
        upgradeGuide('UPGRADE-6.8.md#deprecated-state-select-styling-members-in-the-order-module'),
    ],
    onEveryUsage: [
        removeProp({
            prop: 'rounded-style',
            message: 'rounded-style of sw-order-state-select-v2 has no effect and is removed in 6.8.',
        }),
        replaceProp({
            prop: 'background-style',
            with: backgroundStyleToStateName,
            message:
                'background-style of sw-order-state-select-v2 is removed in 6.8. Use state-name with the technical name of the state instead.',
        }),
    ],
});
