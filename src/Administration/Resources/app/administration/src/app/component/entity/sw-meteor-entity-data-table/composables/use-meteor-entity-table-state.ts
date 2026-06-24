/**
 * @sw-package framework
 */

/* eslint-disable sw-deprecation-rules/private-feature-declarations */

import { reactive, ref } from 'vue';
import type {
    MeteorEntityTableRecord,
    MeteorEntityTableRepository,
    MeteorEntityTableState,
} from '../sw-meteor-entity-data-table.types';
import { getRecordsFromResult, getStateSnapshot, getTotalFromResult } from '../sw-meteor-entity-data-table.utils';
import type { MeteorEntityTableLoadSuccessPayload } from '../sw-meteor-entity-data-table.types';
import type Criteria from 'src/core/data/criteria.data';

type UseMeteorEntityTableStateOptions = {
    getRepository: () => MeteorEntityTableRepository | null;
    getContext: () => unknown;
    emit: {
        (event: 'load-success', payload: MeteorEntityTableLoadSuccessPayload): void;
        (event: 'load-error', payload: unknown): void;
    };
    buildCriteria: () => Promise<Criteria | null>;
    initialPage: number;
    initialLimit: number;
    initialSearchTerm: string;
    initialSortBy: string;
    initialSortDirection: 'ASC' | 'DESC';
    initialNaturalSorting: boolean;
};

export function useMeteorEntityTableState(options: UseMeteorEntityTableStateOptions) {
    const records = ref<MeteorEntityTableRecord[]>([]);
    const total = ref(0);
    const loading = ref(false);
    const error = ref<unknown>(null);
    const requestSequence = ref(0);

    const state = reactive<MeteorEntityTableState>({
        page: options.initialPage,
        limit: options.initialLimit,
        searchTerm: options.initialSearchTerm,
        sortBy: options.initialSortBy,
        sortDirection: options.initialSortDirection,
        naturalSorting: options.initialNaturalSorting,
    });

    const load = async (): Promise<MeteorEntityTableRecord[]> => {
        const requestId = requestSequence.value + 1;
        requestSequence.value = requestId;
        loading.value = true;
        error.value = null;

        try {
            const criteria = await options.buildCriteria();

            if (criteria === null) {
                if (requestId === requestSequence.value) {
                    records.value = [];
                    total.value = 0;
                    loading.value = false;
                }

                return [];
            }

            const repository = options.getRepository();

            if (!repository) {
                throw new Error('sw-meteor-entity-data-table requires either a repository or an entity.');
            }

            const result = await repository.search(criteria, options.getContext());

            if (requestId !== requestSequence.value) {
                return records.value;
            }

            const loadedRecords = getRecordsFromResult(result);
            records.value = loadedRecords;
            total.value = getTotalFromResult(result, loadedRecords);
            loading.value = false;

            options.emit('load-success', {
                records: loadedRecords,
                total: total.value,
                state: getStateSnapshot(state),
            });

            return loadedRecords;
        } catch (loadError: unknown) {
            if (requestId === requestSequence.value) {
                error.value = loadError;
                loading.value = false;
                options.emit('load-error', loadError);
            }

            return records.value;
        }
    };

    const reload = () => {
        return load();
    };

    const setPage = (page: number) => {
        state.page = page;

        return load();
    };

    const setLimit = (limit: number) => {
        state.limit = limit;

        return load();
    };

    const setSearchTerm = (searchTerm: string) => {
        state.searchTerm = searchTerm;

        return load();
    };

    const setSort = (sortBy: string, sortDirection: 'ASC' | 'DESC', naturalSorting = false) => {
        state.sortBy = sortBy;
        state.sortDirection = sortDirection;
        state.naturalSorting = naturalSorting;

        return load();
    };

    return {
        records,
        total,
        loading,
        error,
        state,
        load,
        reload,
        setPage,
        setLimit,
        setSearchTerm,
        setSort,
    };
}
