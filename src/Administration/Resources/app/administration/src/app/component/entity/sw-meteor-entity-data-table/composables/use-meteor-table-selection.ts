/**
 * @sw-package framework
 */

/* eslint-disable sw-deprecation-rules/private-feature-declarations */

import { ref } from 'vue';
import type { Ref } from 'vue';
import type {
    MultipleSelectionChangePayload,
    SelectionChangePayload,
    SwMeteorEntityDataTableRecord,
    SwMeteorEntityDataTableRecords,
    SwMeteorEntityDataTableSelection,
} from '../sw-meteor-entity-data-table.internal-types';

type UseMeteorTableSelectionOptions = {
    records: Ref<SwMeteorEntityDataTableRecords>;
    emitSelectionChange: (selection: SwMeteorEntityDataTableSelection, selectionCount: number) => void;
    emitSelectedIdsChange: (selectedIds: string[]) => void;
};

export function useMeteorTableSelection(options: UseMeteorTableSelectionOptions): {
    selectedIds: Ref<string[]>;
    selectedRecords: Ref<SwMeteorEntityDataTableSelection>;
    setSelectedIds: (selectedIds: string[]) => void;
    onSelectionChange: (payload: SelectionChangePayload) => void;
    onMultipleSelectionChange: (payload: MultipleSelectionChangePayload) => void;
    syncSelectedRecordsWithLoadedRecords: () => void;
} {
    const selectedIds = ref<string[]>([]);
    const selectedRecords = ref<SwMeteorEntityDataTableSelection>({});

    function setSelectedIds(nextSelectedIds: string[]): void {
        const uniqueSelectedIds = nextSelectedIds.filter((id, index) => nextSelectedIds.indexOf(id) === index);

        selectedIds.value = uniqueSelectedIds;
        selectedRecords.value = buildSelectedRecords(uniqueSelectedIds);

        // Keep selection-change compatible with legacy sw-data-grid consumers.
        options.emitSelectionChange({ ...selectedRecords.value }, uniqueSelectedIds.length);
        options.emitSelectedIdsChange([
            ...uniqueSelectedIds,
        ]);
    }

    function buildSelectedRecords(selectedRecordIds: string[]): SwMeteorEntityDataTableSelection {
        return selectedRecordIds.reduce<SwMeteorEntityDataTableSelection>((selection, id) => {
            selection[id] = findRecordById(id) ?? selectedRecords.value[id] ?? { id };

            return selection;
        }, {});
    }

    function syncSelectedRecordsWithLoadedRecords(): void {
        selectedRecords.value = buildSelectedRecords(selectedIds.value);
    }

    function findRecordById(id: string): SwMeteorEntityDataTableRecord | null {
        return options.records.value.find((record) => record.id === id) ?? null;
    }

    function onSelectionChange(payload: SelectionChangePayload): void {
        if (payload.value) {
            setSelectedIds([
                ...selectedIds.value,
                payload.id,
            ]);
            return;
        }

        setSelectedIds(selectedIds.value.filter((id) => id !== payload.id));
    }

    function areAllPayloadSelectionsSelected(payload: MultipleSelectionChangePayload): boolean {
        return payload.selections.length > 0 && payload.selections.every((id) => selectedIds.value.includes(id));
    }

    function onMultipleSelectionChange(payload: MultipleSelectionChangePayload): void {
        if (payload.value) {
            if (areAllPayloadSelectionsSelected(payload)) {
                setSelectedIds(selectedIds.value.filter((id) => !payload.selections.includes(id)));
                return;
            }

            setSelectedIds([
                ...selectedIds.value,
                ...payload.selections,
            ]);
            return;
        }

        setSelectedIds(selectedIds.value.filter((id) => !payload.selections.includes(id)));
    }

    return {
        selectedIds,
        selectedRecords,
        setSelectedIds,
        onSelectionChange,
        onMultipleSelectionChange,
        syncSelectedRecordsWithLoadedRecords,
    };
}
