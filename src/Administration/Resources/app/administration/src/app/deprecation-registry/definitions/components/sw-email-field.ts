import {
    componentMigration,
    mapPropValue,
    reference,
    removeEvent,
    removeProp,
    renameComponent,
    renameEvent,
    renameProp,
    renameVModelArgument,
    slotToProp,
} from '../helpers';

export default componentMigration({
    id: 'component.sw-email-field',
    component: 'sw-email-field',
    replacement: 'mt-email-field',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description:
        'The legacy sw-email-field component is replaced by mt-email-field. Legacy value APIs, medium size, invalid state, AI badge, base-field-mounted, and label slot need migration.',
    handler: 'mt-email-field',
    references: [
        reference({ type: 'upgrade', target: 'UPGRADE-6.7.md#removal-of-sw-email-field' }),
    ],
    usage: [
        renameComponent({ from: 'sw-email-field', to: 'mt-email-field' }),
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
        removeProp({
            prop: 'is-invalid',
        }),
        removeEvent({ event: 'base-field-mounted' }),
        removeProp({
            prop: 'ai-badge',
        }),
        slotToProp({
            slot: 'label',
            prop: 'label',
            fix: 'unsafe-auto',
        }),
    ],
});
