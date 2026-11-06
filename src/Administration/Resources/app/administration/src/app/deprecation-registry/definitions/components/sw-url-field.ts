/**
 * @sw-package framework
 */

import {
    componentMigration,
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
    id: 'component.sw-url-field',
    component: 'sw-url-field',
    replacement: 'mt-url-field',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description:
        'The legacy sw-url-field component is replaced by mt-url-field. Legacy value APIs and text slots need migration.',
    references: [
        upgradeGuide('UPGRADE-6.7.md#removal-of-sw-url-field'),
    ],
    onConvert: [
        renameComponent({ from: 'sw-url-field', to: 'mt-url-field' }),
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
        slotTextToProp({
            fromSlot: 'label',
            toProp: 'label',
        }),
    ],
});
