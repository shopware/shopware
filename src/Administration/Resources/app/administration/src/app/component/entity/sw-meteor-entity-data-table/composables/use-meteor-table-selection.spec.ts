/**
 * @sw-package framework
 */

import { ref } from 'vue';
import { useMeteorTableSelection } from './use-meteor-table-selection';
import type { SwMeteorEntityDataTableRecords } from '../sw-meteor-entity-data-table.internal-types';

describe('src/app/component/entity/sw-meteor-entity-data-table/composables/use-meteor-table-selection', () => {
    it('deduplicates selected ids and emits legacy-compatible payloads', () => {
        const records = ref([
            {
                id: 'record-1',
                name: 'First',
            },
        ] as unknown as SwMeteorEntityDataTableRecords);
        const emitSelectionChange = jest.fn<void, [Record<string, { id: string }>, number]>();
        const emitSelectedIdsChange = jest.fn<void, [string[]]>();
        const selection = useMeteorTableSelection({
            records,
            emitSelectionChange,
            emitSelectedIdsChange,
        });

        selection.setSelectedIds([
            'record-1',
            'record-1',
            'missing-record',
        ]);

        expect(selection.selectedIds.value).toEqual([
            'record-1',
            'missing-record',
        ]);
        expect(emitSelectionChange.mock.calls[0]?.[0]['record-1']?.id).toBe('record-1');
        expect(emitSelectionChange.mock.calls[0]?.[0]['missing-record']).toEqual({ id: 'missing-record' });
        expect(emitSelectionChange.mock.calls[0]?.[1]).toBe(2);
        expect(emitSelectedIdsChange).toHaveBeenCalledWith([
            'record-1',
            'missing-record',
        ]);
    });

    it('toggles already selected bulk payloads off', () => {
        const records = ref([] as unknown as SwMeteorEntityDataTableRecords);
        const selection = useMeteorTableSelection({
            records,
            emitSelectionChange: jest.fn(),
            emitSelectedIdsChange: jest.fn(),
        });

        selection.setSelectedIds([
            'record-1',
            'record-2',
        ]);
        selection.onMultipleSelectionChange({
            selections: [
                'record-1',
                'record-2',
            ],
            value: true,
        });

        expect(selection.selectedIds.value).toEqual([]);
    });
});
