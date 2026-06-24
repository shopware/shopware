import {
    componentMigration,
    reference,
    removeProp,
    removeSlot,
    renameComponent,
    renameProp,
    renameVModelArgument,
    slotToProp,
} from '../helpers';

export default componentMigration({
    id: 'component.sw-switch-field',
    component: 'sw-switch-field',
    replacement: 'mt-switch',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description:
        'The legacy sw-switch-field component is replaced by mt-switch. Switch model and several layout props changed.',
    handler: 'mt-switch',
    references: [
        reference({ type: 'upgrade', target: 'UPGRADE-6.7.md#removal-of-sw-switch-field' }),
    ],
    usage: [
        renameComponent({ from: 'sw-switch-field', to: 'mt-switch' }),
        renameVModelArgument({ from: 'value', to: null }),
        renameProp({
            from: 'no-margin-top',
            to: 'remove-top-margin',
        }),
        removeProp({
            prop: 'size',
        }),
        removeProp({
            prop: 'id',
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
        slotToProp({
            slot: 'label',
            prop: 'label',
            fix: 'unsafe-auto',
        }),
        removeSlot({ slot: 'hint' }),
    ],
});
