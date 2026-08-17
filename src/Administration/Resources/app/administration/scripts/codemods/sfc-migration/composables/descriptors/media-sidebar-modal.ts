/**
 * @sw-package framework
 * @private
 */

import { type ComposableDescriptor, methodMembers, refMembers } from '../types';

const MEDIA_SIDEBAR_MODAL_DESCRIPTOR: ComposableDescriptor = {
    id: 'media-sidebar-modal',
    mixinNames: ['media-sidebar-modal-mixin'],
    import: { source: 'src/app/composables/use-media-sidebar-modal', name: 'useMediaSidebarModal' },
    members: {
        ...refMembers([
            'showModalReplace',
            'showModalDelete',
            'showFolderSettings',
            'showFolderDissolve',
            'showModalMove',
        ]),
        ...methodMembers([
            'openModalReplace',
            'closeModalReplace',
            'openModalDelete',
            'closeModalDelete',
            'openFolderSettings',
            'closeFolderSettings',
            'openFolderDissolve',
            'closeFolderDissolve',
            'openModalMove',
            'closeModalMove',
            'deleteSelectedItems',
            'onFolderDissolved',
            'onFolderMoved',
        ]),
    },
    // The three handlers close their own modal before emitting; every open/close writes its flag.
    internallyReferencedMembers: [
        'showModalReplace',
        'showModalDelete',
        'showFolderSettings',
        'showFolderDissolve',
        'showModalMove',
        'closeModalDelete',
        'closeFolderDissolve',
        'closeModalMove',
    ],
    // The mixin injected both for its own permission checks; the composable resolves them itself.
    unmappedMembers: [
        'acl',
        'mediaService',
    ],
    emits: {
        onItemsDelete: 'media-sidebar-items-delete',
        onFolderItemsDissolve: 'media-sidebar-folder-items-dissolve',
        onItemsMove: 'media-sidebar-items-move',
    },
};

export default MEDIA_SIDEBAR_MODAL_DESCRIPTOR;
