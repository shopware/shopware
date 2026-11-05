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
    id: 'component.sw-email-field',
    component: 'sw-email-field',
    replacement: 'mt-email-field',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description:
        'The legacy sw-email-field component is replaced by mt-email-field. Legacy value APIs, medium size, invalid state, AI badge, base-field-mounted, and label slot need migration.',
    references: [
        upgradeGuide('UPGRADE-6.7.md#removal-of-sw-email-field'),
    ],
    onConvert: [
        renameComponent({ from: 'sw-email-field', to: 'mt-email-field' }),
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
