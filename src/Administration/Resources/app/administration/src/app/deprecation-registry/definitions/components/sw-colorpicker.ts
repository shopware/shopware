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
    id: 'component.sw-colorpicker',
    component: 'sw-colorpicker',
    replacement: 'mt-colorpicker',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description:
        'The legacy sw-colorpicker component is replaced by mt-colorpicker. Value binding uses model-value/update:model-value and the label slot should become the label prop when possible.',
    handler: 'mt-colorpicker',
    references: [
        reference({ type: 'upgrade', target: 'UPGRADE-6.7.md#removal-of-sw-colorpicker' }),
    ],
    usage: [
        renameComponent({ from: 'sw-colorpicker', to: 'mt-colorpicker', fix: 'unsafe-auto' }),
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
