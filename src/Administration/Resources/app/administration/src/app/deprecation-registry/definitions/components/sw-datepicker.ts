/**
 * @sw-package framework
 */

import {
    componentMigration,
    renameComponent,
    renameEvent,
    renameProp,
    renameVModelArgument,
    slotTextToProp,
    upgradeGuide,
} from '../helpers';

export default componentMigration({
    id: 'component.sw-datepicker',
    component: 'sw-datepicker',
    replacement: 'mt-datepicker',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description:
        'The legacy sw-datepicker component is replaced by mt-datepicker. Value binding uses model-value/update:model-value and the label slot should become the label prop when possible.',
    references: [
        upgradeGuide('UPGRADE-6.7.md#removal-of-sw-datepicker'),
    ],
    onConvert: [
        renameComponent({ from: 'sw-datepicker', to: 'mt-datepicker', fix: 'unsafe-auto' }),
    ],
    onEveryUsage: [
        renameProp({
            from: 'value',
            to: 'model-value',
        }),
        renameEvent({ from: 'update:value', to: 'update:model-value' }),
        renameVModelArgument({ from: 'value', to: null }),
        slotTextToProp({
            fromSlot: 'label',
            toProp: 'label',
        }),
    ],
});
