/**
 * @sw-package framework
 */

import {
    componentMigration,
    removeProp,
    removeSlot,
    renameComponent,
    renameEvent,
    renameProp,
    renameVModelArgument,
    slotTextToProp,
    upgradeGuide,
} from '../helpers';

export default componentMigration({
    id: 'component.sw-switch-field',
    component: 'sw-switch-field',
    replacement: 'mt-switch',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description:
        'The legacy sw-switch-field component is replaced by mt-switch. Switch model and several layout props changed.',
    references: [
        upgradeGuide('UPGRADE-6.7.md#removal-of-sw-switch-field'),
    ],
    onConvert: [
        renameComponent({ from: 'sw-switch-field', to: 'mt-switch' }),
        removeProp({
            prop: 'id',
        }),
    ],
    onEveryUsage: [
        renameVModelArgument({ from: 'value', to: null }),
        renameEvent({ from: 'update:value', to: 'update:modelValue' }),
        renameProp({
            from: 'no-margin-top',
            to: 'remove-top-margin',
        }),
        removeProp({
            prop: 'size',
        }),
        renameProp({
            from: 'value',
            to: 'model-value',
        }),
        removeProp({
            prop: 'ghost-value',
        }),
        removeProp({
            prop: 'padded',
        }),
        removeProp({
            prop: 'partly-checked',
        }),
        slotTextToProp({
            fromSlot: 'label',
            toProp: 'label',
        }),
        removeSlot({
            slot: 'hint',
            hint: 'mt-switch has no "hint" slot, so this content is not rendered. Use the "help-text" prop or render it next to the switch.',
        }),
    ],
});
