import {
    componentMigration,
    customUsage,
    reference,
    removeProp,
    renameComponent,
    renameEvent,
    renameProp,
    renameVModelArgument,
    slotToProp,
} from '../helpers';

export default componentMigration({
    id: 'component.sw-select-field',
    component: 'sw-select-field',
    replacement: 'mt-select',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description:
        'The legacy sw-select-field component is replaced by mt-select. Value binding uses Vue 3 modelValue/update:model-value semantics and options must use label/value objects.',
    handler: 'mt-select',
    references: [
        reference({ type: 'upgrade', target: 'UPGRADE-6.7.md#removal-of-sw-select-field' }),
    ],
    usage: [
        renameComponent({ from: 'sw-select-field', to: 'mt-select' }),
        renameProp({
            from: 'value',
            to: 'model-value',
        }),
        renameEvent({ from: 'update:value', to: 'update:model-value' }),
        renameVModelArgument({ from: 'value', to: null }),
        removeProp({
            prop: 'aside',
        }),
        customUsage({ name: 'select-options-name-id-to-label-value', fix: 'manual' }),
        slotToProp({
            slot: 'label',
            prop: 'label',
            fix: 'unsafe-auto',
        }),
        customUsage({ name: 'select-default-option-slot-to-options', fix: 'unsafe-auto' }),
    ],
});
