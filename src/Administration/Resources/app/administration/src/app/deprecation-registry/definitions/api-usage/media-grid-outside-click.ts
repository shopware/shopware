/**
 * @sw-package framework
 */

import { apiUsageMigration, componentMember, templateAttribute, upgradeGuide } from '../helpers';

const MESSAGE =
    'sw-media-grid no longer clears the selection on an outside click in 6.8. Listen to media-item-selection-clear of sw-media-quickinfo-multiple instead.';

export default apiUsageMigration({
    id: 'api.sw-media-grid.outside-click',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'sw-media-grid no longer clears the media selection on a click outside of the grid.',
    references: [
        upgradeGuide('UPGRADE-6.8.md#removed-outside-click-deselection-of-sw-media-grid'),
    ],
    usage: [
        componentMember({
            components: ['sw-media-grid'],
            names: [
                'nonDeselectingComponents',
                'beforeDestroyComponent',
                'clearSelectionOnClickOutside',
                'originatesFromExcludedComponent',
                'isEmittedFromChildren',
                'emitSelectionCleared',
            ],
            message: MESSAGE,
        }),
        componentMember({
            components: ['sw-media-grid'],
            names: ['createdComponent'],
            message:
                "sw-media-grid no longer has createdComponent() in 6.8, nothing calls it and $super('createdComponent') fails. Move your code to created() of the override.",
        }),
        templateAttribute({ elements: ['sw-media-grid'], names: ['@media-grid-selection-clear'], message: MESSAGE }),
    ],
});
