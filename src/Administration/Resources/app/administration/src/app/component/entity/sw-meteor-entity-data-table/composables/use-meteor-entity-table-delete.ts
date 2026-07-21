/**
 * @sw-package framework
 */

/* eslint-disable sw-deprecation-rules/private-feature-declarations */

import { ref } from 'vue';
import type { MeteorEntityTableRecord, MeteorEntityTableRepository } from '../sw-meteor-entity-data-table.types';

type UseMeteorEntityTableDeleteOptions = {
    getRepository: () => MeteorEntityTableRepository | null;
    getContext: () => unknown;
    reload: () => Promise<MeteorEntityTableRecord[]>;
    getSelectedIds: () => string[];
    setSelectedIds: (ids: string[]) => void;
    emit: {
        (event: 'delete-item-finish', id: string): void;
        (event: 'delete-item-failed', payload: { id: string; errorResponse: unknown }): void;
        (event: 'items-delete-finish'): void;
        (event: 'delete-items-failed', payload: { selectedIds: string[]; errorResponse: unknown }): void;
    };
};

export function useMeteorEntityTableDelete(options: UseMeteorEntityTableDeleteOptions) {
    const itemToDelete = ref<MeteorEntityTableRecord | null>(null);
    const isDeleting = ref(false);
    const bulkDeleteIds = ref<string[]>([]);
    const isBulkDeleting = ref(false);

    const openDeleteModal = (record: MeteorEntityTableRecord) => {
        itemToDelete.value = record;
    };

    const closeDeleteModal = () => {
        if (!isDeleting.value) {
            itemToDelete.value = null;
        }
    };

    const confirmDelete = async () => {
        const repository = options.getRepository();

        if (!itemToDelete.value || !repository?.delete) {
            return;
        }

        const id = itemToDelete.value.id;
        isDeleting.value = true;

        try {
            await repository.delete(id, options.getContext());
            options.setSelectedIds(options.getSelectedIds().filter((selectedId) => selectedId !== id));
            itemToDelete.value = null;
            options.emit('delete-item-finish', id);
            await options.reload();
        } catch (error: unknown) {
            options.emit('delete-item-failed', {
                id,
                errorResponse: error,
            });
        } finally {
            isDeleting.value = false;
        }
    };

    const openBulkDeleteModal = () => {
        bulkDeleteIds.value = [...options.getSelectedIds()];
    };

    const closeBulkDeleteModal = () => {
        if (!isBulkDeleting.value) {
            bulkDeleteIds.value = [];
        }
    };

    const confirmBulkDelete = async () => {
        const selectedIds = [...bulkDeleteIds.value];

        if (selectedIds.length < 1) {
            return;
        }

        isBulkDeleting.value = true;

        try {
            const repository = options.getRepository();

            if (repository?.syncDeleted) {
                await repository.syncDeleted(selectedIds, options.getContext());
            } else if (repository?.delete) {
                const deleteItem = repository.delete;

                await Promise.all(selectedIds.map((id) => deleteItem(id, options.getContext())));
            }

            options.setSelectedIds([]);
            bulkDeleteIds.value = [];
            options.emit('items-delete-finish');
            await options.reload();
        } catch (error: unknown) {
            options.emit('delete-items-failed', {
                selectedIds,
                errorResponse: error,
            });
        } finally {
            isBulkDeleting.value = false;
        }
    };

    return {
        itemToDelete,
        isDeleting,
        bulkDeleteIds,
        isBulkDeleting,
        openDeleteModal,
        closeDeleteModal,
        confirmDelete,
        openBulkDeleteModal,
        closeBulkDeleteModal,
        confirmBulkDelete,
    };
}
