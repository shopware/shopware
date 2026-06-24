/**
 * @sw-package framework
 */

/* eslint-disable sw-deprecation-rules/private-feature-declarations */

import { computed, nextTick, shallowRef } from 'vue';
import type {
    MeteorEntityTableRecord,
    MeteorEntityTableRoute,
    MeteorEntityTableRouteQuery,
    MeteorEntityTableRouteQueryKeys,
    MeteorEntityTableRouteQueryValue,
    MeteorEntityTableRouter,
    MeteorEntityTableState,
} from '../sw-meteor-entity-data-table.types';

type RouteUpdateMode = 'push' | 'replace';

type UseMeteorEntityTableRouteSyncOptions = {
    state: MeteorEntityTableState;
    initialState: MeteorEntityTableState;
    getSyncRouteQuery: () => boolean;
    getRouteQueryKeys: () => Partial<MeteorEntityTableRouteQueryKeys>;
    getRoute: () => MeteorEntityTableRoute | undefined;
    getRouter: () => MeteorEntityTableRouter | undefined;
    reload: () => Promise<MeteorEntityTableRecord[]>;
    emitSearchTermChange: (searchTerm: string) => void;
};

const DEFAULT_ROUTE_QUERY_KEYS: MeteorEntityTableRouteQueryKeys = {
    page: 'page',
    limit: 'limit',
    term: 'term',
    sortBy: 'sortBy',
    sortDirection: 'sortDirection',
    naturalSorting: 'naturalSorting',
};

function normalizeRouteQueryKeys(routeQueryKeys: Partial<MeteorEntityTableRouteQueryKeys>): MeteorEntityTableRouteQueryKeys {
    return {
        ...DEFAULT_ROUTE_QUERY_KEYS,
        ...routeQueryKeys,
    };
}

function getQueryValue(value: MeteorEntityTableRouteQueryValue): string | number | boolean | null | undefined {
    return Array.isArray(value) ? value[0] : value;
}

function hasQueryKey(query: MeteorEntityTableRouteQuery, key: string): boolean {
    return Object.prototype.hasOwnProperty.call(query, key);
}

function getPositiveIntegerQueryValue(value: MeteorEntityTableRouteQueryValue, fallback: number): number {
    const parsedValue = Number.parseInt(String(getQueryValue(value) ?? ''), 10);

    return Number.isFinite(parsedValue) && parsedValue > 0 ? parsedValue : fallback;
}

function getStringQueryValue(query: MeteorEntityTableRouteQuery, key: string, fallback: string): string {
    if (!hasQueryKey(query, key)) {
        return fallback;
    }

    const queryValue = getQueryValue(query[key]);

    if (queryValue === undefined || queryValue === null) {
        return fallback;
    }

    return String(queryValue);
}

function getSortDirectionQueryValue(value: MeteorEntityTableRouteQueryValue, fallback: 'ASC' | 'DESC'): 'ASC' | 'DESC' {
    const direction = String(getQueryValue(value) ?? '').toUpperCase();

    return direction === 'ASC' || direction === 'DESC' ? direction : fallback;
}

function getBooleanQueryValue(value: MeteorEntityTableRouteQueryValue, fallback: boolean): boolean {
    const normalizedValue = String(getQueryValue(value) ?? '').toLowerCase();

    if (normalizedValue === 'true') {
        return true;
    }

    if (normalizedValue === 'false') {
        return false;
    }

    return fallback;
}

function routeQueryValuesAreEqual(left: MeteorEntityTableRouteQueryValue, right: MeteorEntityTableRouteQueryValue): boolean {
    const normalizedLeft = getQueryValue(left);
    const normalizedRight = getQueryValue(right);

    if (normalizedLeft === undefined || normalizedLeft === null || normalizedLeft === '') {
        return normalizedRight === undefined || normalizedRight === null || normalizedRight === '';
    }

    return String(normalizedLeft) === String(normalizedRight);
}

export function useMeteorEntityTableRouteSync(options: UseMeteorEntityTableRouteSyncOptions) {
    const hasLoaded = shallowRef(false);
    const skipNextCriteriaPageReset = shallowRef(false);
    const skipNextSearchTermPropReload = shallowRef(false);
    const isUpdatingRouteQuery = shallowRef(false);
    const resolvedRouteQueryKeys = computed(() => {
        return normalizeRouteQueryKeys(options.getRouteQueryKeys());
    });

    const markStateChangeFromRoute = () => {
        skipNextCriteriaPageReset.value = true;

        void nextTick(() => {
            skipNextCriteriaPageReset.value = false;
        });
    };

    const syncState = (nextState: MeteorEntityTableState) => {
        let changed = false;

        if (options.state.page !== nextState.page) {
            options.state.page = nextState.page;
            changed = true;
        }

        if (options.state.limit !== nextState.limit) {
            options.state.limit = nextState.limit;
            changed = true;
        }

        if (options.state.searchTerm !== nextState.searchTerm) {
            options.state.searchTerm = nextState.searchTerm;
            changed = true;
        }

        if (options.state.sortBy !== nextState.sortBy) {
            options.state.sortBy = nextState.sortBy;
            changed = true;
        }

        if (options.state.sortDirection !== nextState.sortDirection) {
            options.state.sortDirection = nextState.sortDirection;
            changed = true;
        }

        if (options.state.naturalSorting !== nextState.naturalSorting) {
            options.state.naturalSorting = nextState.naturalSorting;
            changed = true;
        }

        return changed;
    };

    const emitSearchTermBridge = (searchTerm: string) => {
        skipNextSearchTermPropReload.value = true;
        options.emitSearchTermChange(searchTerm);

        void nextTick(() => {
            skipNextSearchTermPropReload.value = false;
        });
    };

    const getRouteStateFromQuery = (query: MeteorEntityTableRouteQuery = {}): MeteorEntityTableState => {
        const routeQueryKeys = resolvedRouteQueryKeys.value;

        return {
            page: getPositiveIntegerQueryValue(query[routeQueryKeys.page], options.initialState.page),
            limit: getPositiveIntegerQueryValue(query[routeQueryKeys.limit], options.initialState.limit),
            searchTerm: getStringQueryValue(query, routeQueryKeys.term, options.initialState.searchTerm),
            sortBy: getStringQueryValue(query, routeQueryKeys.sortBy, options.initialState.sortBy),
            sortDirection: getSortDirectionQueryValue(
                query[routeQueryKeys.sortDirection],
                options.initialState.sortDirection,
            ),
            naturalSorting: getBooleanQueryValue(query[routeQueryKeys.naturalSorting], options.initialState.naturalSorting),
        };
    };

    const syncStateFromRouteQuery = (query: MeteorEntityTableRouteQuery = {}) => {
        const nextState = getRouteStateFromQuery(query);
        const previousSearchTerm = options.state.searchTerm;
        const changed = syncState(nextState);

        if (previousSearchTerm !== nextState.searchTerm) {
            emitSearchTermBridge(nextState.searchTerm);
        }

        return changed;
    };

    const getRouteQueryForState = (): MeteorEntityTableRouteQuery => {
        const routeQueryKeys = resolvedRouteQueryKeys.value;

        return {
            [routeQueryKeys.page]: options.state.page,
            [routeQueryKeys.limit]: options.state.limit,
            [routeQueryKeys.term]: options.state.searchTerm,
            [routeQueryKeys.sortBy]: options.state.sortBy,
            [routeQueryKeys.sortDirection]: options.state.sortDirection,
            [routeQueryKeys.naturalSorting]: options.state.naturalSorting,
        };
    };

    const routeQueryNeedsUpdate = (query: MeteorEntityTableRouteQuery) => {
        const routeStateQuery = getRouteQueryForState();

        return Object.entries(routeStateQuery).some(
            ([
                key,
                value,
            ]) => {
                return !routeQueryValuesAreEqual(query[key], value);
            },
        );
    };

    const updateRouteQuery = (mode: RouteUpdateMode) => {
        if (!options.getSyncRouteQuery()) {
            return;
        }

        const route = options.getRoute();
        const router = options.getRouter();
        const updateRoute = router?.[mode];

        if (!route || typeof updateRoute !== 'function') {
            return;
        }

        const currentQuery = route.query ?? {};

        if (!routeQueryNeedsUpdate(currentQuery)) {
            return;
        }

        const nextRoute = {
            name: route.name,
            params: route.params,
            query: {
                ...currentQuery,
                ...getRouteQueryForState(),
            },
        };

        isUpdatingRouteQuery.value = true;

        void Promise.resolve(updateRoute(nextRoute)).finally(() => {
            void nextTick(() => {
                isUpdatingRouteQuery.value = false;
            });
        });
    };

    const syncInitialRouteState = () => {
        if (!options.getSyncRouteQuery()) {
            return;
        }

        syncStateFromRouteQuery(options.getRoute()?.query ?? {});
        updateRouteQuery('replace');
    };

    const getRouteQuerySnapshot = () => {
        return {
            ...(options.getRoute()?.query ?? {}),
        };
    };

    const syncRouteQueryState = async (query: MeteorEntityTableRouteQuery = {}) => {
        if (!options.getSyncRouteQuery() || !hasLoaded.value || isUpdatingRouteQuery.value) {
            return;
        }

        const changed = syncStateFromRouteQuery(query);

        if (changed) {
            markStateChangeFromRoute();
            await options.reload();
        }
    };

    const shouldSkipSearchTermPropReload = (searchTerm: string | null | undefined) => {
        if (skipNextSearchTermPropReload.value && searchTerm === options.state.searchTerm) {
            skipNextSearchTermPropReload.value = false;

            return true;
        }

        return false;
    };

    const shouldSkipCriteriaPageReset = () => {
        return skipNextCriteriaPageReset.value;
    };

    const clearCriteriaPageResetSkip = () => {
        skipNextCriteriaPageReset.value = false;
    };

    const markLoaded = () => {
        hasLoaded.value = true;
    };

    const isLoaded = () => {
        return hasLoaded.value;
    };

    return {
        syncState,
        syncInitialRouteState,
        syncRouteQueryState,
        getRouteQuerySnapshot,
        updateRouteQuery,
        emitSearchTermBridge,
        markStateChangeFromRoute,
        markLoaded,
        shouldSkipSearchTermPropReload,
        shouldSkipCriteriaPageReset,
        clearCriteriaPageResetSkip,
        isLoaded,
    };
}
