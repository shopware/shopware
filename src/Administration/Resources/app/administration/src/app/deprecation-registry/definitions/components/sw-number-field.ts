import {
    componentMigration,
    reference,
    renameComponent,
    renameEvent,
    renameProp,
    renameVModelArgument,
    slotToProp,
} from '../helpers';

export default componentMigration({
    id: 'component.sw-number-field',
    component: 'sw-number-field',
    replacement: 'mt-number-field',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description:
        'The legacy sw-number-field component is replaced by mt-number-field. Legacy value APIs and label slot need migration.',
    handler: 'mt-number-field',
    references: [
        reference({ type: 'upgrade', target: 'UPGRADE-6.7.md#removal-of-sw-number-field' }),
    ],
    usage: [
        renameComponent({ from: 'sw-number-field', to: 'mt-number-field' }),
        renameProp({
            from: 'value',
            to: 'model-value',
        }),
        renameEvent({ from: 'update:value', to: 'update:model-value' }),
        renameVModelArgument({ from: 'value', to: null }),
        slotToProp({
            slot: 'label',
            prop: 'label',
            fix: 'unsafe-auto',
        }),
    ],
});
