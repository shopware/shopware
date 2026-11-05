/**
 * @sw-package framework
 */

import {
    componentMigration,
    mapPropValue,
    renameComponent,
    renameEvent,
    renameProp,
    renameVModelArgument,
    slotToProp,
    upgradeGuide,
} from '../helpers';

export default componentMigration({
    id: 'component.sw-number-field',
    component: 'sw-number-field',
    replacement: 'mt-number-field',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description:
        'The legacy sw-number-field component is replaced by mt-number-field. Legacy value APIs and label slot need migration.',
    references: [
        upgradeGuide('UPGRADE-6.7.md#removal-of-sw-number-field'),
    ],
    onConvert: [
        renameComponent({ from: 'sw-number-field', to: 'mt-number-field' }),
    ],
    onEveryUsage: [
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
        slotToProp({
            slot: 'label',
            prop: 'label',
        }),
    ],
});
