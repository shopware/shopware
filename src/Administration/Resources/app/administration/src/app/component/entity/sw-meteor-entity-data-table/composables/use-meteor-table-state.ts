/**
 * @sw-package framework
 */

/* eslint-disable sw-deprecation-rules/private-feature-declarations */

import { ref } from 'vue';
import type { Ref } from 'vue';
import type {
    SwMeteorEntityDataTableSortDirection,
    SwMeteorEntityDataTableState,
} from '../sw-meteor-entity-data-table.types';

type UseMeteorTableStateOptions = {
    initialPage: () => number | undefined;
    initialLimit: () => number | undefined;
    initialSearchTerm: () => string | undefined;
    initialSort: () => SwMeteorEntityDataTableState['sort'] | null | undefined;
    emitStateChange: (state: SwMeteorEntityDataTableState) => void;
    load: () => Promise<void>;
};

export function useMeteorTableState(options: UseMeteorTableStateOptions): {
    state: Ref<SwMeteorEntityDataTableState>;
    buildStateFromProps: () => SwMeteorEntityDataTableState;
    syncStateFromProps: () => boolean;
    cloneState: () => SwMeteorEntityDataTableState;
    setPage: (page: number) => Promise<void>;
    setLimit: (limit: number) => Promise<void>;
    setSearchTerm: (term: string) => Promise<void>;
    setSort: (property: string, direction: SwMeteorEntityDataTableSortDirection) => Promise<void>;
} {
    function buildStateFromProps(): SwMeteorEntityDataTableState {
        const initialSort = options.initialSort();

        return {
            page: options.initialPage() ?? 1,
            limit: options.initialLimit() ?? 25,
            searchTerm: options.initialSearchTerm() ?? '',
            ...(initialSort
                ? {
                      sort: {
                          ...initialSort,
                      },
                  }
                : {}),
        };
    }

    const state = ref<SwMeteorEntityDataTableState>(buildStateFromProps());

    function syncStateFromProps(): boolean {
        const nextState = buildStateFromProps();

        if (areStatesEqual(state.value, nextState)) {
            return false;
        }

        state.value = nextState;

        return true;
    }

    function cloneState(): SwMeteorEntityDataTableState {
        return cloneMeteorTableState(state.value);
    }

    function emitStateChange(): void {
        options.emitStateChange(cloneState());
    }

    function setPage(nextPage: number): Promise<void> {
        state.value = {
            ...state.value,
            page: nextPage,
        };

        emitStateChange();

        return options.load();
    }

    function setLimit(nextLimit: number): Promise<void> {
        state.value = {
            ...state.value,
            page: 1,
            limit: nextLimit,
        };

        emitStateChange();

        return options.load();
    }

    function setSearchTerm(term: string): Promise<void> {
        state.value = {
            ...state.value,
            page: 1,
            searchTerm: term,
        };

        emitStateChange();

        return options.load();
    }

    function setSort(property: string, direction: SwMeteorEntityDataTableSortDirection): Promise<void> {
        state.value = {
            ...state.value,
            page: 1,
            sort: {
                property,
                direction,
            },
        };

        emitStateChange();

        return options.load();
    }

    return {
        state,
        buildStateFromProps,
        syncStateFromProps,
        cloneState,
        setPage,
        setLimit,
        setSearchTerm,
        setSort,
    };
}

export function cloneMeteorTableState(currentState: SwMeteorEntityDataTableState): SwMeteorEntityDataTableState {
    return {
        page: currentState.page,
        limit: currentState.limit,
        searchTerm: currentState.searchTerm,
        ...(currentState.sort
            ? {
                  sort: {
                      ...currentState.sort,
                  },
              }
            : {}),
    };
}

export function areSortsEqual(
    currentSort: SwMeteorEntityDataTableState['sort'],
    nextSort: SwMeteorEntityDataTableState['sort'],
): boolean {
    if (!currentSort && !nextSort) {
        return true;
    }

    if (!currentSort || !nextSort) {
        return false;
    }

    return currentSort.property === nextSort.property && currentSort.direction === nextSort.direction;
}

export function areStatesEqual(
    currentState: SwMeteorEntityDataTableState,
    nextState: SwMeteorEntityDataTableState,
): boolean {
    return (
        currentState.page === nextState.page &&
        currentState.limit === nextState.limit &&
        currentState.searchTerm === nextState.searchTerm &&
        areSortsEqual(currentState.sort, nextState.sort)
    );
}
