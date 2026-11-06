/**
 * @sw-package framework
 */

import { componentMigration, removeProp, renameComponent, upgradeGuide } from '../helpers';

export default componentMigration({
    id: 'component.sw-entity-many-to-many-select',
    component: 'sw-entity-many-to-many-select',
    replacement: 'sw-entity-multi-select',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description:
        'sw-entity-many-to-many-select is replaced by sw-entity-multi-select, which never saves the assignment on its own.',
    references: [
        upgradeGuide('UPGRADE-6.8.md#removed-sw-select-number-field-sw-entity-many-to-many-select-and-sw-help-sidebar'),
    ],
    onConvert: [
        renameComponent({ from: 'sw-entity-many-to-many-select', to: 'sw-entity-multi-select' }),
        removeProp({
            prop: 'local-mode',
            reviewUnlessTrue:
                'sw-entity-many-to-many-select saved the assignment immediately without local mode. Save the entity collection when the parent entity is saved.',
        }),
    ],
});
