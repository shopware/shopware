import {
    componentMigration,
    reference,
    removeSlot,
    renameComponent,
    renameEvent,
    renameProp,
    renameVModelArgument,
    slotToProp,
} from '../helpers';

export default componentMigration({
    id: 'component.sw-url-field',
    component: 'sw-url-field',
    replacement: 'mt-url-field',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description:
        'The legacy sw-url-field component is replaced by mt-url-field. Legacy value APIs and text slots need migration.',
    handler: 'mt-url-field',
    references: [
        reference({ type: 'upgrade', target: 'UPGRADE-6.7.md#removal-of-sw-url-field' }),
    ],
    usage: [
        renameComponent({ from: 'sw-url-field', to: 'mt-url-field' }),
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
        removeSlot({ slot: 'hint' }),
    ],
});
