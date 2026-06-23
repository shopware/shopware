/**
 * @sw-package framework
 */

/* eslint-disable sw-deprecation-rules/private-feature-declarations */

import { ref } from 'vue';
import type { MeteorEntityTableRecord, MeteorEntityTableSelection } from '../sw-meteor-entity-data-table.types';

export function useMeteorEntityTableSelection(records: () => MeteorEntityTableRecord[]) {
    const selectedIds = ref<string[]>([]);
    const selection = ref<MeteorEntityTableSelection>({});

    const rebuildSelection = (pruneSelectedIds = false) => {
        const loadedById = new Map(
            records().map((record) => [
                record.id,
                record,
            ]),
        );

        if (pruneSelectedIds) {
            selectedIds.value = selectedIds.value.filter((id) => loadedById.has(id));
        }

        selection.value = selectedIds.value.reduce<MeteorEntityTableSelection>((accumulator, id) => {
            const record = loadedById.get(id);

            if (record) {
                accumulator[id] = record;
            }

            return accumulator;
        }, {});
    };

    const setSelectedIds = (ids: string[]) => {
        selectedIds.value = Array.from(new Set(ids));
        rebuildSelection();
    };

    const pruneSelection = () => {
        rebuildSelection(true);
    };

    return {
        selectedIds,
        selection,
        setSelectedIds,
        rebuildSelection,
        pruneSelection,
    };
}
