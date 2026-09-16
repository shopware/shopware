/**
 * @sw-package discovery
 *
 * @experimental stableVersion:v6.9.0 feature:ADMIN_MIXIN_COMPOSABLES
 */
import { nextTick, ref, type Ref } from 'vue';

/**
 * The mixin emitted its three events off the instance. A composable has no `$emit`, so the events
 * arrive as callbacks named after what happened.
 *
 * @private
 */
export interface UseMediaSidebarModalOptions {
    onItemsDelete: (ids: string[]) => void;
    onFolderItemsDissolve: (ids: string[]) => void;
    onItemsMove: (ids: string[]) => void;
}

/**
 * Composable alternative to the `media-sidebar-modal-mixin`: the open/close state of the media
 * sidebar's modals, plus the three handlers that close a modal and report what it did.
 *
 * Each handler waits a tick before reporting, because the modal is torn down by the same flag flip and
 * the parent's reaction typically reloads the list the modal was rendered into. The mixin injected
 * `acl` for its permission checks; this composable resolves it through `Shopware.Service` instead, so
 * it needs nothing from the component. The mixin stays in place for Options API components.
 *
 * Keep this and `src/module/sw-media/mixin/media-sidebar-modal.mixin.js` in sync — change both
 * together.
 *
 * @private
 */
export default function useMediaSidebarModal(options: UseMediaSidebarModalOptions): {
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
    deleteSelectedItems: (ids: string[]) => void;
    onFolderDissolved: (ids: string[]) => void;
    onFolderMoved: (ids: string[]) => void;
} {
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

    function deleteSelectedItems(ids: string[]): void {
        closeModalDelete();

        void nextTick(() => {
            options.onItemsDelete(ids);
        });
    }

    function onFolderDissolved(ids: string[]): void {
        closeFolderDissolve();

        void nextTick(() => {
            options.onFolderItemsDissolve(ids);
        });
    }

    function onFolderMoved(ids: string[]): void {
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
