/**
 * @sw-package framework
 */

import { ref } from 'vue';
import type { ApiContext } from '@shopware-ag/meteor-admin-sdk/es/_internals/data/EntityCollection';
import type Repository from 'src/core/data/repository.data';
import { useMeteorTableDeleteActions } from './use-meteor-table-delete-actions';

describe('src/app/component/entity/sw-meteor-entity-data-table/composables/use-meteor-table-delete-actions', () => {
    it('guards the bulk delete modal behind selection and delete permission', () => {
        const deleteActions = useMeteorTableDeleteActions({
            repository: () => ({}) as unknown as Repository<keyof EntitySchema.Entities>,
            context: () => null,
            selectable: () => true,
            allowDelete: () => true,
            selectedIds: ref([]),
            setSelectedIds: jest.fn(),
            load: jest.fn<Promise<void>, []>().mockResolvedValue(),
            emitBulkDeleteFailed: jest.fn(),
            emitBulkDeleteFinish: jest.fn(),
            emitDeleteFailed: jest.fn(),
            emitDeleteFinish: jest.fn(),
        });

        deleteActions.openBulkDeleteModal();

        expect(deleteActions.showBulkDeleteModal.value).toBe(false);
    });

    it('clears selection and reloads after successful bulk deletion', async () => {
        const syncDeleted = jest.fn<Promise<void>, [string[], ApiContext]>().mockResolvedValue();
        const selectedIds = ref([
            'record-1',
            'record-2',
        ]);
        const setSelectedIds = jest.fn();
        const load = jest.fn<Promise<void>, []>().mockResolvedValue();
        const emitBulkDeleteFinish = jest.fn();
        const deleteActions = useMeteorTableDeleteActions({
            repository: () =>
                ({
                    syncDeleted,
                }) as unknown as Repository<keyof EntitySchema.Entities>,
            context: () => null,
            selectable: () => true,
            allowDelete: () => true,
            selectedIds,
            setSelectedIds,
            load,
            emitBulkDeleteFailed: jest.fn(),
            emitBulkDeleteFinish,
            emitDeleteFailed: jest.fn(),
            emitDeleteFinish: jest.fn(),
        });

        deleteActions.openBulkDeleteModal();
        await deleteActions.deleteSelectedRecords();

        expect(syncDeleted).toHaveBeenCalledWith(
            [
                'record-1',
                'record-2',
            ],
            Shopware.Context.api,
        );
        expect(setSelectedIds).toHaveBeenCalledWith([]);
        expect(emitBulkDeleteFinish).toHaveBeenCalledWith({
            ids: [
                'record-1',
                'record-2',
            ],
        });
        expect(load).toHaveBeenCalledTimes(1);
        expect(deleteActions.showBulkDeleteModal.value).toBe(false);
    });

    it('keeps the single-delete modal open when deletion fails', async () => {
        const deleteRecord = jest.fn<Promise<unknown>, [string, ApiContext]>().mockRejectedValue(new Error('failed'));
        const emitDeleteFailed = jest.fn();
        const deleteActions = useMeteorTableDeleteActions({
            repository: () =>
                ({
                    delete: deleteRecord,
                }) as unknown as Repository<keyof EntitySchema.Entities>,
            context: () => null,
            selectable: () => false,
            allowDelete: () => true,
            selectedIds: ref([]),
            setSelectedIds: jest.fn(),
            load: jest.fn<Promise<void>, []>().mockResolvedValue(),
            emitBulkDeleteFailed: jest.fn(),
            emitBulkDeleteFinish: jest.fn(),
            emitDeleteFailed,
            emitDeleteFinish: jest.fn(),
        });
        const record = {
            id: 'record-1',
        };

        deleteActions.openDeleteModal(record);
        await deleteActions.deleteRecord();

        expect(deleteActions.itemToDelete.value).toEqual(record);
        expect(emitDeleteFailed).toHaveBeenCalledWith(
            expect.objectContaining({
                id: 'record-1',
                record,
            }),
        );
    });
});
