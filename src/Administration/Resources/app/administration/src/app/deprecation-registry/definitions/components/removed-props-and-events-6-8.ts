/**
 * @sw-package framework
 */

import { componentMigration, removeEvent, removeProp, upgradeGuide } from '../helpers';

export default [
    componentMigration({
        id: 'component.sw-order-state-history-modal.is-loading',
        component: 'sw-order-state-history-modal',
        deprecatedIn: '6.7.0',
        removedIn: '6.8.0',
        description: 'sw-order-state-history-modal no longer has the isLoading prop.',
        references: [upgradeGuide('UPGRADE-6.8.md#removed-unused-order-components')],
        onEveryUsage: [removeProp({ prop: 'is-loading' })],
    }),
    componentMigration({
        id: 'component.sw-media-grid.selection-clear',
        component: 'sw-media-grid',
        deprecatedIn: '6.7.0',
        removedIn: '6.8.0',
        description:
            'sw-media-grid no longer clears the selection on an outside click and no longer emits media-grid-selection-clear.',
        references: [upgradeGuide('UPGRADE-6.8.md#removed-outside-click-deselection-of-sw-media-grid')],
        onEveryUsage: [
            removeEvent({
                event: 'media-grid-selection-clear',
                fix: 'manual',
                message: 'Listen to media-item-selection-clear of sw-media-quickinfo-multiple instead.',
            }),
        ],
    }),
];
