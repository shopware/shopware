/**
 * @sw-package framework
 */

import { computed } from 'vue';
import type { ApiContext } from '@shopware-ag/meteor-admin-sdk/es/_internals/data/EntityCollection';
import type Repository from 'src/core/data/repository.data';
import { useMeteorTableInlineEdit } from './use-meteor-table-inline-edit';

describe('src/app/component/entity/sw-meteor-entity-data-table/composables/use-meteor-table-inline-edit', () => {
    function createInlineEdit() {
        return useMeteorTableInlineEdit({
            repository: () => ({}) as unknown as Repository<keyof EntitySchema.Entities>,
            context: () => null,
            allowInlineEdit: () => false,
            inlineEditableColumns: computed(() => []),
            load: jest.fn<Promise<void>, []>().mockResolvedValue(),
            emitInlineEditSave: jest.fn(),
            emitInlineEditCancel: jest.fn(),
        });
    }

    it('keeps a failed save in edit mode and clears loading state', async () => {
        const record = {
            id: 'record-1',
            name: 'First',
        };
        const save = jest.fn<Promise<void>, [typeof record, ApiContext]>().mockRejectedValue(new Error('failed'));
        const inlineEdit = useMeteorTableInlineEdit({
            repository: () =>
                ({
                    save,
                }) as unknown as Repository<keyof EntitySchema.Entities>,
            context: () => null,
            allowInlineEdit: () => true,
            inlineEditableColumns: computed(() => [
                {
                    property: 'name',
                    label: 'Name',
                    renderer: 'text',
                    position: 0,
                    inlineEdit: 'string',
                },
            ]),
            load: jest.fn<Promise<void>, []>().mockResolvedValue(),
            emitInlineEditSave: jest.fn(),
            emitInlineEditCancel: jest.fn(),
        });

        inlineEdit.startInlineEdit(record);
        await inlineEdit.saveInlineEdit(record);
        await new Promise<void>((resolve) => {
            setTimeout(resolve);
        });

        expect(inlineEdit.isInlineEditing(record)).toBe(true);
        expect(inlineEdit.savingInlineEdit.value).toBe(false);
    });

    it('updates nested record values through object paths', () => {
        const record = {
            id: 'record-1',
            translated: {
                name: 'Old',
            },
        };
        const inlineEdit = createInlineEdit();

        inlineEdit.updateRecordValue(record, 'translated.name', 'New');

        expect(inlineEdit.getRecordValue(record, 'translated.name')).toBe('New');
        expect(inlineEdit.renderRecordValue(record, 'translated.name')).toBe('New');
    });

    it.each([
        [
            'zero',
            { quantity: 0 },
            '0',
        ],
        [
            'positive number',
            { quantity: 12.5 },
            '12.5',
        ],
        [
            'numeric string',
            { quantity: '0012.50' },
            '12.5',
        ],
        [
            'null',
            { quantity: null },
            '',
        ],
        [
            'undefined',
            { quantity: undefined },
            '',
        ],
        [
            'missing property',
            {},
            '',
        ],
        [
            'empty string',
            { quantity: '' },
            '',
        ],
        [
            'whitespace-only string',
            { quantity: '   ' },
            '',
        ],
        [
            'non-numeric string',
            { quantity: 'not-a-number' },
            '',
        ],
    ])('renders number record values for %s', (description, values, expectedValue) => {
        const inlineEdit = createInlineEdit();
        const record = {
            id: 'record-1',
            name: description,
            ...values,
        };

        expect(inlineEdit.renderNumberRecordValue(record, 'quantity')).toBe(expectedValue);
    });
});
