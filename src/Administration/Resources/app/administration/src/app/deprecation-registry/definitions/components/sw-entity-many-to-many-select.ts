/**
 * @sw-package framework
 */

import { componentMigration, removeProp, renameComponent, upgradeGuide } from '../helpers';

export default componentMigration({
    id: 'component.sw-entity-many-to-many-select',
    component: 'sw-entity-many-to-many-select',
    replacement: 'sw-entity-multi-select',
    deprecatedIn: '6.7.3.0',
    removedIn: '6.8.0',
    description:
        'sw-entity-many-to-many-select is replaced by sw-entity-multi-select, which never saves the assignment on its own, like sw-entity-many-to-many-select in local mode.',
    references: [
        upgradeGuide('UPGRADE-6.8.md#removed-sw-select-number-field-sw-entity-many-to-many-select-and-sw-help-sidebar'),
    ],
    onConvert: [
        renameComponent({ from: 'sw-entity-many-to-many-select', to: 'sw-entity-multi-select' }),
        removeProp({
            prop: 'local-mode',
            reviewWhenFalse:
                'sw-entity-many-to-many-select saved every change of the assignment without local-mode, sw-entity-multi-select never does: save the entity collection together with its parent entity.',
        }),
    ],
});
