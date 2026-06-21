import {
    addBooleanPropTransform,
    componentMigration,
    mapPropValue,
    reference,
    removeProp,
    renameComponent,
} from '../helpers';

export default componentMigration({
    id: 'component.sw-button',
    component: 'sw-button',
    replacement: 'mt-button',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description:
        'The legacy sw-button component is replaced by mt-button. Several legacy variant values and router-link usage need migration.',
    handler: 'mt-button',
    references: [
        reference({ type: 'upgrade', target: 'UPGRADE-6.7.md#removal-of-sw-button' }),
    ],
    usage: [
        renameComponent({ from: 'sw-button', to: 'mt-button' }),
        mapPropValue({
            prop: 'variant',
            from: 'ghost',
            to: 'primary',
            transform: addBooleanPropTransform({ prop: 'ghost' }),
        }),
        mapPropValue({
            prop: 'variant',
            from: 'danger',
            to: 'critical',
        }),
        mapPropValue({
            prop: 'variant',
            from: 'ghost-danger',
            to: 'critical',
            transform: addBooleanPropTransform({ prop: 'ghost' }),
        }),
        removeProp({
            prop: 'router-link',
            fix: 'manual',
            message:
                'Replace router-link with an explicit click handler or router-link wrapper and verify navigation semantics.',
        }),
    ],
});
