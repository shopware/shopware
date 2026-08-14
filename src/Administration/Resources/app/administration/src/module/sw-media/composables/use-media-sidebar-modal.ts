/**
 * @sw-package discovery
 */
import { nextTick, ref } from 'vue';
import type { Ref } from 'vue';

/**
 * Callbacks for the three events the mixin emitted on the host component.
 *
 * @private
 */
export interface UseMediaSidebarModalOptions {
    onItemsDelete: (ids: unknown) => void;
    onFolderItemsDissolve: (ids: unknown) => void;
    onItemsMove: (ids: unknown) => void;
}

/** @private */
export interface UseMediaSidebarModalReturn {
    showModalReplace: Ref<boolean>;
    showModalDelete: Ref<boolean>;
    showFolderSettings: Ref<boolean>;
    showFolderDissolve: Ref<boolean>;
    showModalMove: Ref<boolean>;
    openModalReplace: () => void;
    closeModalReplace: () => void;
    openModalDelete: () => void;
    closeModalDelete: () => void;
    openFolderSettings: () => void;
    closeFolderSettings: () => void;
    openFolderDissolve: () => void;
    closeFolderDissolve: () => void;
    openModalMove: () => void;
    closeModalMove: () => void;
    deleteSelectedItems: (ids: unknown) => void;
    onFolderDissolved: (ids: unknown) => void;
    onFolderMoved: (ids: unknown) => void;
}

/**
 * Composable alternative to the `media-sidebar-modal-mixin`: owns the open/close
 * state of the media sidebar modals. The mixin injected `acl` and emitted three
 * events on the host component; here `acl` comes from `Shopware.Service` and the
 * events are passed in as callbacks, because a composable has no `$emit`.
 *
 * Keep this and `src/module/sw-media/mixin/media-sidebar-modal.mixin.js` in sync —
 * change both together.
 *
 * @private
 */
export function useMediaSidebarModal(options: UseMediaSidebarModalOptions): UseMediaSidebarModalReturn {
    const showModalReplace = ref(false);
    const showModalDelete = ref(false);
    const showFolderSettings = ref(false);
    const showFolderDissolve = ref(false);
    const showModalMove = ref(false);

    function acl(): { can: (privilege: string) => boolean } {
        return Shopware.Service('acl');
    }

    function openModalReplace(): void {
        if (!acl().can('media.editor')) {
            return;
        }

        showModalReplace.value = true;
    }

    function closeModalReplace(): void {
        showModalReplace.value = false;
    }

    function openModalDelete(): void {
        if (!acl().can('media.deleter')) {
            return;
        }

        showModalDelete.value = true;
    }

    function closeModalDelete(): void {
        showModalDelete.value = false;
    }

    function openFolderSettings(): void {
        showFolderSettings.value = true;
    }

    function closeFolderSettings(): void {
        showFolderSettings.value = false;
    }

    function openFolderDissolve(): void {
        if (!acl().can('media.editor')) {
            return;
        }

        showFolderDissolve.value = true;
    }

    function closeFolderDissolve(): void {
        showFolderDissolve.value = false;
    }

    function openModalMove(): void {
        if (!acl().can('media.editor')) {
            return;
        }

        showModalMove.value = true;
    }

    function closeModalMove(): void {
        showModalMove.value = false;
    }

    function deleteSelectedItems(ids: unknown): void {
        closeModalDelete();

        void nextTick(() => {
            options.onItemsDelete(ids);
        });
    }

    function onFolderDissolved(ids: unknown): void {
        closeFolderDissolve();

        void nextTick(() => {
            options.onFolderItemsDissolve(ids);
        });
    }

    function onFolderMoved(ids: unknown): void {
        closeModalMove();

        void nextTick(() => {
            options.onItemsMove(ids);
        });
    }

    return {
        showModalReplace,
        showModalDelete,
        showFolderSettings,
        showFolderDissolve,
        showModalMove,
        openModalReplace,
        closeModalReplace,
        openModalDelete,
        closeModalDelete,
        openFolderSettings,
        closeFolderSettings,
        openFolderDissolve,
        closeFolderDissolve,
        openModalMove,
        closeModalMove,
        deleteSelectedItems,
        onFolderDissolved,
        onFolderMoved,
    };
}
