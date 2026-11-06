/**
 * @sw-package framework
 */

import { componentMember, jsApiMigration, upgradeGuide } from '../helpers';

export default jsApiMigration({
    id: 'js-api.media-grid-outside-click',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description:
        'sw-media-grid no longer clears the selection on an outside click. Listen to media-item-selection-clear of sw-media-quickinfo-multiple.',
    references: [upgradeGuide('UPGRADE-6.8.md#removed-outside-click-deselection-of-sw-media-grid')],
    usage: [
        componentMember({ components: ['sw-media-grid'], member: 'nonDeselectingComponents' }),
        componentMember({ components: ['sw-media-grid'], member: 'createdComponent' }),
        componentMember({ components: ['sw-media-grid'], member: 'beforeDestroyComponent' }),
        componentMember({ components: ['sw-media-grid'], member: 'clearSelectionOnClickOutside' }),
        componentMember({ components: ['sw-media-grid'], member: 'originatesFromExcludedComponent' }),
        componentMember({ components: ['sw-media-grid'], member: 'isEmittedFromChildren' }),
        componentMember({ components: ['sw-media-grid'], member: 'emitSelectionCleared' }),
    ],
});
