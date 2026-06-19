/**
 * @sw-package framework
 */

import {
    createRepositoryMock,
    createSearchResult,
    createWrapper,
    flushPromises,
    getSaveMock,
    getSearchMock,
    inlineEditColumns,
    nextTick,
    registerSwMeteorEntityDataTableHooks,
} from './fixtures';

describe('src/app/component/entity/sw-meteor-entity-data-table/inline-edit', () => {
    registerSwMeteorEntityDataTableHooks();

    it('does not enter inline edit mode when inline editing is disabled', async () => {
        const wrapper = createWrapper({
            props: {
                columns: inlineEditColumns,
            },
        });

        await flushPromises();
        await wrapper.find('.sw-meteor-entity-data-table__inline-edit-cell').trigger('dblclick');
        await nextTick();

        expect(wrapper.find('.sw-data-grid-inline-edit-stub').exists()).toBe(false);
    });

    it('enters inline edit mode for editable columns on double click', async () => {
        const wrapper = createWrapper({
            props: {
                columns: inlineEditColumns,
                allowInlineEdit: true,
            },
        });

        await flushPromises();
        await wrapper.find('.sw-meteor-entity-data-table__inline-edit-cell').trigger('dblclick');
        await nextTick();

        expect(wrapper.find('.sw-data-grid-inline-edit-stub').exists()).toBe(true);
        expect(wrapper.find('.sw-meteor-entity-data-table__inline-edit-save').exists()).toBe(true);
        expect(wrapper.find('.sw-meteor-entity-data-table__inline-edit-cancel').exists()).toBe(true);
    });

    it('saves inline edited records and reloads the table', async () => {
        const editableRecords = [
            {
                id: 'record-1',
                name: 'First record',
            },
        ];
        const repository = createRepositoryMock(createSearchResult(editableRecords));
        const wrapper = createWrapper({
            props: {
                repository,
                columns: inlineEditColumns,
                allowInlineEdit: true,
            },
        });

        await flushPromises();
        await wrapper.find('.sw-meteor-entity-data-table__inline-edit-cell').trigger('dblclick');
        await wrapper.find('.sw-data-grid-inline-edit-stub').setValue('Updated record');
        await wrapper.find('.sw-meteor-entity-data-table__inline-edit-save').trigger('click');

        const saveMock = getSaveMock(repository);
        const emittedSave = wrapper.emitted('inline-edit-save');

        expect(saveMock).toHaveBeenCalledWith(
            expect.objectContaining({
                id: 'record-1',
                name: 'Updated record',
            }),
            Shopware.Context.api,
        );
        expect(emittedSave?.[0][0]).toBeInstanceOf(Promise);
        expect(emittedSave?.[0][1]).toEqual(
            expect.objectContaining({
                id: 'record-1',
                name: 'Updated record',
            }),
        );

        await flushPromises();

        expect(getSearchMock(repository)).toHaveBeenCalledTimes(2);
        expect(wrapper.find('.sw-data-grid-inline-edit-stub').exists()).toBe(false);
    });

    it('keeps inline edit mode active when saving fails', async () => {
        const editableRecords = [
            {
                id: 'record-1',
                name: 'First record',
            },
        ];
        const repository = createRepositoryMock(createSearchResult(editableRecords));
        const saveError = new Error('Could not save');

        getSaveMock(repository).mockRejectedValueOnce(saveError);

        const wrapper = createWrapper({
            props: {
                repository,
                columns: inlineEditColumns,
                allowInlineEdit: true,
            },
        });

        await flushPromises();
        await wrapper.find('.sw-meteor-entity-data-table__inline-edit-cell').trigger('dblclick');
        await wrapper.find('.sw-data-grid-inline-edit-stub').setValue('Updated record');
        await wrapper.find('.sw-meteor-entity-data-table__inline-edit-save').trigger('click');

        const emittedSave = wrapper.emitted('inline-edit-save');

        await expect(emittedSave?.[0][0]).rejects.toThrow('Could not save');
        await flushPromises();

        expect(getSearchMock(repository)).toHaveBeenCalledTimes(1);
        expect(wrapper.find('.sw-data-grid-inline-edit-stub').exists()).toBe(true);
    });

    it('cancels inline edits by reloading the table without saving', async () => {
        const editableRecords = [
            {
                id: 'record-1',
                name: 'First record',
            },
        ];
        const repository = createRepositoryMock(createSearchResult(editableRecords));
        const wrapper = createWrapper({
            props: {
                repository,
                columns: inlineEditColumns,
                allowInlineEdit: true,
            },
        });

        await flushPromises();
        await wrapper.find('.sw-meteor-entity-data-table__inline-edit-cell').trigger('dblclick');
        await wrapper.find('.sw-data-grid-inline-edit-stub').setValue('Updated record');
        await wrapper.find('.sw-meteor-entity-data-table__inline-edit-cancel').trigger('click');

        const emittedCancel = wrapper.emitted('inline-edit-cancel');

        expect(getSaveMock(repository)).not.toHaveBeenCalled();
        expect(emittedCancel?.[0][0]).toBeInstanceOf(Promise);

        await flushPromises();

        expect(getSearchMock(repository)).toHaveBeenCalledTimes(2);
        expect(wrapper.find('.sw-data-grid-inline-edit-stub').exists()).toBe(false);
    });
});
