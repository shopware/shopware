/**
 * @sw-package framework
 * @private
 */

import { type ComposableDescriptor, methodMembers, refMembers } from '../types';

const MEDIA_GRID_LISTENER_DESCRIPTOR: ComposableDescriptor = {
    id: 'media-grid-listener',
    mixinNames: ['media-grid-listener'],
    import: { source: 'src/app/composables/use-media-grid-listener', name: 'useMediaGridListener' },
    members: {
        ...refMembers([
            'selectedItems',
            'listSelectionStartItem',
            'mediaItemSelectionHandler',
            'isListSelect',
        ]),
        ...methodMembers([
            'isItemSelected',
            'showItemSelected',
            'clearSelection',
            'navigateToFolder',
            'showDetails',
            'handleMediaItemClicked',
            'handleMediaGridItemSelected',
            'handleMediaGridItemUnselected',
        ]),
    },
    internallyReferencedMembers: [
        'selectedItems',
        'listSelectionStartItem',
        'isListSelect',
        'isItemSelected',
        'navigateToFolder',
        'handleMediaItemClicked',
        'handleMediaGridItemSelected',
        'handleMediaGridItemUnselected',
    ],
    // The mixin's selection bookkeeping, which the composable keeps to itself. `showDetails` is the
    // public equivalent of `_singleSelect`.
    unmappedMembers: [
        '_singleSelect',
        '_startListSelect',
        '_handleSelection',
        '_removeItemFromSelection',
        '_addItemToSelection',
        '_handleShiftSelect',
        '_findSelectionIndices',
    ],
    emits: {
        onFolderChange: 'media-folder-change',
    },
    // The mixin's own `selectableItems` computed returned an empty list; a range selection only
    // works against the host's.
    callbackArgs: [
        { name: 'selectableItems', kind: 'getter' },
    ],
};

export default MEDIA_GRID_LISTENER_DESCRIPTOR;
