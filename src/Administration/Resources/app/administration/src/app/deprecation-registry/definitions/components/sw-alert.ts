/**
 * @sw-package framework
 */

import {
    componentMigration,
    invertBooleanProp,
    mapPropValue,
    removeProp,
    removeSlot,
    renameComponent,
    renameProp,
    upgradeGuide,
} from '../helpers';

export default componentMigration({
    id: 'component.sw-alert',
    component: 'sw-alert',
    replacement: 'mt-banner',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description:
        'The legacy sw-alert component is replaced by mt-banner. Banner prop names and variants changed in the Meteor component library.',
    references: [
        upgradeGuide('UPGRADE-6.7.md#component-replacement-with-meteor-component-library'),
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
        invertBooleanProp({
            from: 'show-icon',
            to: 'hide-icon',
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
        removeSlot({
            slot: 'actions',
            replacementComment: 'Slot "actions" was removed and has no replacement.',
        }),
    ],
});
