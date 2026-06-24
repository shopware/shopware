import {
    componentMigration,
    reference,
    removeProp,
    renameComponent,
    renameEvent,
    renameProp,
    renameVModelArgument,
    slotToProp,
} from '../helpers';

export default componentMigration({
    id: 'component.sw-checkbox-field',
    component: 'sw-checkbox-field',
    replacement: 'mt-checkbox',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description:
        'The legacy sw-checkbox-field component is replaced by mt-checkbox. Checkbox model and several legacy props/slots changed.',
    handler: 'mt-checkbox',
    references: [
        reference({ type: 'upgrade', target: 'UPGRADE-6.7.md#removal-of-sw-checkbox-field' }),
    ],
    usage: [
        renameComponent({ from: 'sw-checkbox-field', to: 'mt-checkbox' }),
        renameProp({
            from: 'value',
            to: 'checked',
        }),
        renameVModelArgument({ from: null, to: 'checked' }),
        renameVModelArgument({ from: 'value', to: 'checked' }),
        slotToProp({
            slot: 'label',
            prop: 'label',
            fix: 'unsafe-auto',
        }),
        slotToProp({
            slot: 'hint',
            prop: 'label',
            fix: 'unsafe-auto',
        }),
        removeProp({
            prop: 'id',
        }),
        removeProp({
            prop: 'ghost-value',
        }),
        renameProp({
            from: 'partly-checked',
            to: 'partial',
        }),
        removeProp({
            prop: 'padded',
        }),
        renameEvent({ from: 'update:value', to: 'update:checked' }),
    ],
});
