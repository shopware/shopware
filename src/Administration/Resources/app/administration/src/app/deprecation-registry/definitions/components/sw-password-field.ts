/**
 * @sw-package framework
 */

import {
    componentMigration,
    mapPropValue,
    removeEvent,
    removeProp,
    renameComponent,
    renameEvent,
    renameProp,
    renameVModelArgument,
    slotToProp,
    upgradeGuide,
} from '../helpers';

export default componentMigration({
    id: 'component.sw-password-field',
    component: 'sw-password-field',
    replacement: 'mt-password-field',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description:
        'The legacy sw-password-field component is replaced by mt-password-field. Legacy value APIs, medium size, invalid state, base-field-mounted, and the label slot need migration.',
    references: [
        upgradeGuide('UPGRADE-6.7.md#removal-of-sw-password-field'),
    ],
    usage: [
        renameComponent({ from: 'sw-password-field', to: 'mt-password-field' }),
        renameProp({
            from: 'value',
            to: 'model-value',
        }),
        renameEvent({ from: 'update:value', to: 'update:model-value' }),
        renameVModelArgument({ from: 'value', to: null }),
        mapPropValue({
            prop: 'size',
            from: 'medium',
            to: 'default',
        }),
        removeProp({
            prop: 'is-invalid',
        }),
        removeEvent({ event: 'base-field-mounted' }),
        slotToProp({
            slot: 'label',
            prop: 'label',
            fix: 'unsafe-auto',
        }),
    ],
});
