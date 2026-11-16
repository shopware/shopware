/**
 * @sw-package framework
 */

import { componentMigration, removeProp, renameComponent, requireTrueProp, upgradeGuide } from '../helpers';

export default componentMigration({
    id: 'component.sw-entity-many-to-many-select',
    component: 'sw-entity-many-to-many-select',
    replacement: 'sw-entity-multi-select',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description:
        'sw-entity-many-to-many-select is replaced by sw-entity-multi-select, which behaves the same in local mode. Without local mode the old component saved the association itself.',
    references: [
        upgradeGuide('UPGRADE-6.8.md#replace-sw-entity-many-to-many-select-with-sw-entity-multi-select'),
    ],
    overrideHint: 'sw-entity-many-to-many-select is removed. Move this override to sw-entity-multi-select.',
    onConvert: [
        renameComponent({ from: 'sw-entity-many-to-many-select', to: 'sw-entity-multi-select' }),
        requireTrueProp({
            prop: 'local-mode',
            message:
                'Use sw-entity-multi-select, which does not save the association itself: handle @update:entity-collection and save the parent entity.',
        }),
        removeProp({ prop: 'local-mode' }),
    ],
});
