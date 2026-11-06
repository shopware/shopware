/**
 * @sw-package framework
 */

import {
    componentMigration,
    removeEvent,
    removeProp,
    renameComponent,
    renameEvent,
    renameProp,
    renameVModelArgument,
    replacePropValue,
    slotTextToProp,
    upgradeGuide,
} from '../helpers';

export default componentMigration({
    id: 'component.sw-text-field',
    component: 'sw-text-field',
    replacement: 'mt-text-field',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description:
        'The legacy sw-text-field component is replaced by mt-text-field. Legacy value APIs, medium size, invalid state, AI badge, base-field-mounted, and label slot need migration.',
    references: [
        upgradeGuide('UPGRADE-6.7.md#removal-of-sw-text-field'),
    ],
    onConvert: [
        renameComponent({ from: 'sw-text-field', to: 'mt-text-field' }),
    ],
    onEveryUsage: [
        renameProp({
            from: 'value',
            to: 'model-value',
        }),
        renameEvent({ from: 'update:value', to: 'update:model-value' }),
        renameVModelArgument({ from: 'value', to: null }),
        replacePropValue({
            prop: 'size',
            fromValue: 'medium',
            toValue: 'default',
        }),
        removeProp({
            prop: 'is-invalid',
        }),
        removeEvent({ event: 'base-field-mounted' }),
        removeProp({
            prop: 'ai-badge',
        }),
        slotTextToProp({
            fromSlot: 'label',
            toProp: 'label',
        }),
    ],
});
