/**
 * @sw-package framework
 */

/* eslint-disable sw-deprecation-rules/private-feature-declarations */

import { ref } from 'vue';
import type { Ref } from 'vue';
import type {
    SwMeteorEntityDataTableProps,
    SwMeteorEntityDataTableRecord,
} from '../sw-meteor-entity-data-table.internal-types';

type UseMeteorTableDeleteActionsOptions = {
    repository: () => SwMeteorEntityDataTableProps['repository'];
    context: () => SwMeteorEntityDataTableProps['context'];
    selectable: () => boolean | undefined;
    allowDelete: () => boolean | undefined;
    selectedIds: Ref<string[]>;
    setSelectedIds: (selectedIds: string[]) => void;
    load: () => Promise<void>;
    emitBulkDeleteFailed: (payload: { ids: string[]; error: unknown }) => void;
    emitBulkDeleteFinish: (payload: { ids: string[] }) => void;
    emitDeleteFailed: (payload: { id: string; record: SwMeteorEntityDataTableRecord; error: unknown }) => void;
    emitDeleteFinish: (payload: { id: string; record: SwMeteorEntityDataTableRecord }) => void;
};

export function useMeteorTableDeleteActions(options: UseMeteorTableDeleteActionsOptions): {
    itemToDelete: Ref<SwMeteorEntityDataTableRecord | null>;
    deleting: Ref<boolean>;
    showBulkDeleteModal: Ref<boolean>;
    bulkDeleting: Ref<boolean>;
    openDeleteModal: (record: SwMeteorEntityDataTableRecord) => void;
    closeDeleteModal: () => void;
    deleteRecord: () => Promise<void>;
    openBulkDeleteModal: () => void;
    closeBulkDeleteModal: () => void;
    deleteSelectedRecords: () => Promise<void>;
} {
    const itemToDelete: Ref<SwMeteorEntityDataTableRecord | null> = ref(null);
    const deleting = ref(false);
    const showBulkDeleteModal = ref(false);
    const bulkDeleting = ref(false);

    function openDeleteModal(record: SwMeteorEntityDataTableRecord): void {
        if (!options.allowDelete()) {
            return;
        }

        itemToDelete.value = record;
    }

    function openBulkDeleteModal(): void {
        if (!options.selectable() || !options.allowDelete() || options.selectedIds.value.length <= 0) {
            return;
        }

        showBulkDeleteModal.value = true;
    }

    function closeBulkDeleteModal(): void {
        if (bulkDeleting.value) {
            return;
        }

        showBulkDeleteModal.value = false;
    }

    async function deleteSelectedRecords(): Promise<void> {
        const ids = [
            ...options.selectedIds.value,
        ];

        if (!options.selectable() || !options.allowDelete() || ids.length <= 0) {
            return;
        }

        bulkDeleting.value = true;

        try {
            const deleteContext = (options.context() ?? Shopware.Context.api) as typeof Shopware.Context.api;

            await options.repository().syncDeleted(ids, deleteContext);
        } catch (error) {
            options.emitBulkDeleteFailed({
                ids,
                error,
            });
            bulkDeleting.value = false;

            return;
        }

        bulkDeleting.value = false;
        showBulkDeleteModal.value = false;
        options.setSelectedIds([]);

        options.emitBulkDeleteFinish({
            ids,
        });

        await options.load();
    }

    function closeDeleteModal(): void {
        if (deleting.value) {
            return;
        }

        itemToDelete.value = null;
    }

    async function deleteRecord(): Promise<void> {
        const record = itemToDelete.value;

        if (!record) {
            return;
        }

        deleting.value = true;

        try {
            const deleteContext = (options.context() ?? Shopware.Context.api) as typeof Shopware.Context.api;

            await options.repository().delete(record.id, deleteContext);
        } catch (error) {
            options.emitDeleteFailed({
                id: record.id,
                record,
                error,
            });
            deleting.value = false;

            return;
        }

        deleting.value = false;
        itemToDelete.value = null;

        if (options.selectedIds.value.includes(record.id)) {
            options.setSelectedIds(options.selectedIds.value.filter((id) => id !== record.id));
        }

        options.emitDeleteFinish({
            id: record.id,
            record,
        });

        await options.load();
    }

    return {
        itemToDelete,
        deleting,
        showBulkDeleteModal,
        bulkDeleting,
        openDeleteModal,
        closeDeleteModal,
        deleteRecord,
        openBulkDeleteModal,
        closeBulkDeleteModal,
        deleteSelectedRecords,
    };
}
