/**
 * @sw-package framework
 */

/* eslint-disable sw-deprecation-rules/private-feature-declarations */

import type {
    MeteorEntityTableRecord,
    MeteorEntityTableResult,
    MeteorEntityTableState,
} from './sw-meteor-entity-data-table.types';

export function getRecordsFromResult(result: MeteorEntityTableResult): MeteorEntityTableRecord[] {
    return Array.from(result);
}

export function getTotalFromResult(result: MeteorEntityTableResult, records: MeteorEntityTableRecord[]): number {
    return typeof result.total === 'number' ? result.total : records.length;
}

export function getStateSnapshot(state: MeteorEntityTableState): MeteorEntityTableState {
    return {
        page: state.page,
        limit: state.limit,
        searchTerm: state.searchTerm,
        sortBy: state.sortBy,
        sortDirection: state.sortDirection,
        naturalSorting: state.naturalSorting,
    };
}
