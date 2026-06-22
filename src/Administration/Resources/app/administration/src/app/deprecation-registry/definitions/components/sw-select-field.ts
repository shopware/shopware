import {
    componentMigration,
    mapOptionsPropKeys,
    reference,
    removeProp,
    renameComponent,
    renameEvent,
    renameProp,
    renameVModelArgument,
    slotToProp,
    slotToPropComment,
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
        mapOptionsPropKeys({
            prop: 'options',
            from: {
                name: 'label',
                id: 'value',
            },
            fix: 'unsafe-auto',
            message: 'Replace option object keys "name"/"id" with "label"/"value".',
            unsafeMessage:
                'Migrate option object keys "name"/"id" to "label"/"value" manually because this options expression is dynamic.',
        }),
        slotToProp({
            slot: 'label',
            prop: 'label',
            fix: 'unsafe-auto',
        }),
        slotToPropComment({ slot: 'default', prop: 'options', fix: 'unsafe-auto' }),
    ],
});
