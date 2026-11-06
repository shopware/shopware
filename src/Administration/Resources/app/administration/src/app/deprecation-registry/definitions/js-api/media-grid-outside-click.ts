/**
 * @sw-package framework
 */

import { componentMembers, jsApiMigration, upgradeGuide } from '../helpers';

export default jsApiMigration({
    id: 'js-api.media-grid-outside-click',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description:
        'sw-media-grid no longer clears the selection on an outside click. Listen to media-item-selection-clear of sw-media-quickinfo-multiple.',
    references: [upgradeGuide('UPGRADE-6.8.md#removed-outside-click-deselection-of-sw-media-grid')],
    usage: [
        ...componentMembers({
            components: ['sw-media-grid'],
            members: [
                'nonDeselectingComponents',
                'createdComponent',
                'beforeDestroyComponent',
                'clearSelectionOnClickOutside',
                'originatesFromExcludedComponent',
                'isEmittedFromChildren',
                'emitSelectionCleared',
            ],
        }),
    ],
});
