/**
 * @sw-package framework
 *
 * The outside-click deselection of `sw-media-grid`, which 6.8 removes in favor of the explicit "Unselect all" of the
 * media sidebar, taken from the `@deprecated tag:v6.8.0` markers. Since 6.7.16.0 the event no longer fires. The
 * sidebar emits `media-item-selection-clear`, which `sw-media-index` and `sw-media-modal-v2` handle.
 */

const removed = (name) => `sw-media-grid.${name} is removed without replacement. The media sidebar clears the selection with media-item-selection-clear instead.`;

module.exports = {
    id: 'media-grid-deselect',
    description: 'Marks the outside-click deselection of sw-media-grid, which 6.8 removes',
    events: [
        { name: 'media-grid-selection-clear', message: 'sw-media-grid no longer clears the selection on an outside click. Listen to media-item-selection-clear from the media sidebar instead.' },
    ],
    members: [
        { component: 'sw-media-grid', name: 'nonDeselectingComponents', message: removed('nonDeselectingComponents') },
        { component: 'sw-media-grid', name: 'clearSelectionOnClickOutside', message: removed('clearSelectionOnClickOutside()') },
        { component: 'sw-media-grid', name: 'originatesFromExcludedComponent', message: removed('originatesFromExcludedComponent()') },
        { component: 'sw-media-grid', name: 'isEmittedFromChildren', message: removed('isEmittedFromChildren()') },
        { component: 'sw-media-grid', name: 'emitSelectionCleared', message: removed('emitSelectionCleared()') },
        { component: 'sw-media-grid', name: 'createdComponent', kinds: ['methods'], message: removed('createdComponent()') },
        { component: 'sw-media-grid', name: 'beforeDestroyComponent', kinds: ['methods'], message: removed('beforeDestroyComponent()') },
    ],
};
