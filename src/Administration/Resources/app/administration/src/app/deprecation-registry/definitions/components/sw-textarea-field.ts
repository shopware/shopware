/**
 * @sw-package framework
 */

import {
    componentMigration,
    renameComponent,
    renameEvent,
    renameProp,
    renameVModelArgument,
    slotToProp,
    upgradeGuide,
} from '../helpers';

export default componentMigration({
    id: 'component.sw-textarea-field',
    component: 'sw-textarea-field',
    replacement: 'mt-textarea',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description:
        'The legacy sw-textarea-field component is replaced by mt-textarea. Value binding uses model-value/update:model-value and the label slot should become the label prop when it contains plain text.',
    references: [
        upgradeGuide('UPGRADE-6.7.md#removal-of-sw-textarea-field'),
    ],
    onConvert: [
        renameComponent({ from: 'sw-textarea-field', to: 'mt-textarea', fix: 'unsafe-auto' }),
    ],
    onEveryUsage: [
        renameProp({
            from: 'value',
            to: 'model-value',
        }),
        renameEvent({ from: 'update:value', to: 'update:model-value' }),
        renameVModelArgument({ from: 'value', to: null }),
        slotToProp({
            slot: 'label',
            prop: 'label',
        }),
    ],
});
