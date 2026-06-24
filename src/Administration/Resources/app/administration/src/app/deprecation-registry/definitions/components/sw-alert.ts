import {
    componentMigration,
    invertBooleanTransform,
    mapPropValue,
    reference,
    removeProp,
    removeSlot,
    renameComponent,
    renameProp,
} from '../helpers';

export default componentMigration({
    id: 'component.sw-alert',
    component: 'sw-alert',
    replacement: 'mt-banner',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description:
        'The legacy sw-alert component is replaced by mt-banner. Banner prop names and variants changed in the Meteor component library.',
    handler: 'mt-banner',
    references: [
        reference({ type: 'upgrade', target: 'UPGRADE-6.7.md#removal-of-sw-alert' }),
    ],
    usage: [
        renameComponent({ from: 'sw-alert', to: 'mt-banner' }),
        renameProp({
            from: 'notification-index',
            to: 'banner-index',
        }),
        removeProp({
            prop: 'appearance',
        }),
        renameProp({
            from: 'show-icon',
            to: 'hide-icon',
            transform: invertBooleanTransform,
        }),
        mapPropValue({
            prop: 'variant',
            from: 'warning',
            to: 'attention',
        }),
        mapPropValue({
            prop: 'variant',
            from: 'error',
            to: 'critical',
        }),
        mapPropValue({
            prop: 'variant',
            from: 'success',
            to: 'positive',
        }),
        removeSlot({ slot: 'actions' }),
    ],
});
