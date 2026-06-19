/**
 * @sw-package framework
 */

import {
    createRepositoryMock,
    createWrapper,
    flushPromises,
    getDeleteMock,
    getSearchMock,
    getSyncDeletedMock,
    getTable,
    nextTick,
    records,
    registerSwMeteorEntityDataTableHooks,
} from './fixtures';

describe('src/app/component/entity/sw-meteor-entity-data-table/deletion', () => {
    registerSwMeteorEntityDataTableHooks();

    it('opens a bulk delete confirmation modal and deletes selected rows', async () => {
        const repository = createRepositoryMock();
        const wrapper = createWrapper({
            props: {
                repository,
                selectable: true,
                allowDelete: true,
            },
        });

        await flushPromises();
        getSearchMock(repository).mockClear();

        await wrapper.find('.mt-data-table-stub__select-all').trigger('click');
        await wrapper.find('.mt-data-table-stub__bulk-delete').trigger('click');
        await nextTick();

        expect(wrapper.find('.sw-meteor-entity-data-table__bulk-delete-modal').exists()).toBe(true);

        await wrapper.find('.sw-meteor-entity-data-table__bulk-delete-confirm').trigger('click');
        await flushPromises();

        expect(getSyncDeletedMock(repository)).toHaveBeenCalledWith(
            [
                'record-1',
                'record-2',
            ],
            Shopware.Context.api,
        );
        expect(getDeleteMock(repository)).not.toHaveBeenCalled();
        expect(getTable(wrapper).props('selectedRows')).toEqual([]);
        expect(getSearchMock(repository)).toHaveBeenCalledTimes(1);
        expect(wrapper.find('.sw-meteor-entity-data-table__bulk-delete-modal').exists()).toBe(false);
        expect(wrapper.emitted('bulk-delete-finish')).toEqual([
            [
                {
                    ids: [
                        'record-1',
                        'record-2',
                    ],
                },
            ],
        ]);
    });

    it('emits bulk-delete-failed and keeps the modal open when bulk deletion fails', async () => {
        const repository = createRepositoryMock();
        const error = new Error('Bulk delete failed');
        getSyncDeletedMock(repository).mockRejectedValueOnce(error);

        const wrapper = createWrapper({
            props: {
                repository,
                selectable: true,
                allowDelete: true,
            },
        });

        await flushPromises();
        getSearchMock(repository).mockClear();

        await wrapper.find('.mt-data-table-stub__select-row').trigger('click');
        await wrapper.find('.mt-data-table-stub__bulk-delete').trigger('click');
        await wrapper.find('.sw-meteor-entity-data-table__bulk-delete-confirm').trigger('click');
        await flushPromises();

        expect(getSearchMock(repository)).not.toHaveBeenCalled();
        expect(getTable(wrapper).props('selectedRows')).toEqual(['record-1']);
        expect(wrapper.find('.sw-meteor-entity-data-table__bulk-delete-modal').exists()).toBe(true);
        expect(wrapper.emitted('bulk-delete-failed')).toEqual([
            [
                {
                    ids: ['record-1'],
                    error,
                },
            ],
        ]);
    });

    it('does not open the bulk delete modal without selected rows or delete permission', async () => {
        const wrapper = createWrapper({
            props: {
                selectable: true,
                allowDelete: true,
            },
        });
        const wrapperWithoutDeletePermission = createWrapper({
            props: {
                selectable: true,
            },
        });

        await flushPromises();
        await wrapper.find('.mt-data-table-stub__bulk-delete').trigger('click');
        await wrapperWithoutDeletePermission.find('.mt-data-table-stub__select-row').trigger('click');
        await wrapperWithoutDeletePermission.find('.mt-data-table-stub__bulk-delete').trigger('click');
        await nextTick();

        expect(wrapper.find('.sw-meteor-entity-data-table__bulk-delete-modal').exists()).toBe(false);
        expect(wrapperWithoutDeletePermission.find('.sw-meteor-entity-data-table__bulk-delete-modal').exists()).toBe(false);
    });

    it('opens a delete confirmation modal and deletes the selected row', async () => {
        const repository = createRepositoryMock();
        const wrapper = createWrapper({
            props: {
                repository,
                allowDelete: true,
            },
        });

        await flushPromises();
        getSearchMock(repository).mockClear();

        await wrapper.find('.mt-data-table-stub__delete').trigger('click');
        await nextTick();

        expect(wrapper.find('.sw-meteor-entity-data-table__delete-modal').exists()).toBe(true);

        await wrapper.find('.sw-meteor-entity-data-table__delete-confirm').trigger('click');
        await flushPromises();

        expect(getDeleteMock(repository)).toHaveBeenCalledWith('record-1', Shopware.Context.api);
        expect(getSearchMock(repository)).toHaveBeenCalledTimes(1);
        expect(wrapper.find('.sw-meteor-entity-data-table__delete-modal').exists()).toBe(false);
        expect(wrapper.emitted('delete-finish')).toEqual([
            [
                {
                    id: 'record-1',
                    record: records[0],
                },
            ],
        ]);
    });

    it('emits delete-failed and keeps the modal open when deletion fails', async () => {
        const repository = createRepositoryMock();
        const error = new Error('Delete failed');
        getDeleteMock(repository).mockRejectedValueOnce(error);

        const wrapper = createWrapper({
            props: {
                repository,
                allowDelete: true,
            },
        });

        await flushPromises();
        getSearchMock(repository).mockClear();

        await wrapper.find('.mt-data-table-stub__delete').trigger('click');
        await wrapper.find('.sw-meteor-entity-data-table__delete-confirm').trigger('click');
        await flushPromises();

        expect(getSearchMock(repository)).not.toHaveBeenCalled();
        expect(wrapper.find('.sw-meteor-entity-data-table__delete-modal').exists()).toBe(true);
        expect(wrapper.emitted('delete-failed')).toEqual([
            [
                {
                    id: 'record-1',
                    record: records[0],
                    error,
                },
            ],
        ]);
    });

    it('does not open the delete modal when row deletion is disabled', async () => {
        const wrapper = createWrapper();

        await flushPromises();
        await wrapper.find('.mt-data-table-stub__delete').trigger('click');
        await nextTick();

        expect(wrapper.find('.sw-meteor-entity-data-table__delete-modal').exists()).toBe(false);
    });
});
