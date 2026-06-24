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
    id: 'component.sw-datepicker',
    component: 'sw-datepicker',
    replacement: 'mt-datepicker',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description:
        'The legacy sw-datepicker component is replaced by mt-datepicker. Value binding uses model-value/update:model-value and the label slot should become the label prop when possible.',
    handler: 'mt-datepicker',
    references: [
        reference({ type: 'upgrade', target: 'UPGRADE-6.7.md#removal-of-sw-datepicker' }),
    ],
    usage: [
        renameComponent({ from: 'sw-datepicker', to: 'mt-datepicker', fix: 'unsafe-auto' }),
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
