/**
 * @sw-package framework
 */

/* eslint-disable sw-deprecation-rules/private-feature-declarations */

import { ref } from 'vue';
import type { MeteorEntityTableRecord, MeteorEntityTableRepository } from '../sw-meteor-entity-data-table.types';

type UseMeteorEntityTableDeleteOptions = {
    repository: MeteorEntityTableRepository;
    context?: unknown;
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
        if (!itemToDelete.value || !options.repository.delete) {
            return;
        }

        const id = itemToDelete.value.id;
        isDeleting.value = true;

        try {
            await options.repository.delete(id, options.context);
            options.setSelectedIds(options.getSelectedIds().filter((selectedId) => selectedId !== id));
            itemToDelete.value = null;
            options.emit('delete-item-finish', id);
            await options.reload();
        } catch (errorResponse: unknown) {
            options.emit('delete-item-failed', {
                id,
                errorResponse,
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
            if (options.repository.syncDeleted) {
                await options.repository.syncDeleted(selectedIds, options.context);
            } else if (options.repository.delete) {
                const deleteItem = options.repository.delete;

                await Promise.all(selectedIds.map((id) => deleteItem(id, options.context)));
            }

            options.setSelectedIds([]);
            bulkDeleteIds.value = [];
            options.emit('items-delete-finish');
            await options.reload();
        } catch (errorResponse: unknown) {
            options.emit('delete-items-failed', {
                selectedIds,
                errorResponse,
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
