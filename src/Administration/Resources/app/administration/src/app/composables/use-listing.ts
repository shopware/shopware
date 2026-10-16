/**
 * @sw-package framework
 *
 * @experimental stableVersion:v6.9.0 feature:ADMIN_MIXIN_COMPOSABLES
 */
import { computed, onMounted, ref, watch } from 'vue';
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router';
import type { ComputedRef, Ref } from 'vue';
import type { LocationQuery, RouteLocationNamedRaw } from 'vue-router';
import type Criteria from '@shopware-ag/meteor-admin-sdk/es/data/Criteria';

/* The listing contract predates typing; several values cross untyped service boundaries */
/* eslint-disable @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access,@typescript-eslint/no-unsafe-return,@typescript-eslint/no-explicit-any */

/** @private */
export interface ListingFilter {
    active: boolean;
    [key: string]: any;
}

/** @private */
export interface ListingParams {
    limit?: unknown;
    page?: unknown;
    term?: unknown;
    sortBy?: unknown;
    sortDirection?: unknown;
    naturalSorting?: unknown;
}

/**
 * The mixin drove an abstract `getList()` the component implemented, and read a `filters` computed the
 * component was invited to override. A composable can declare neither, so both are handed in. Every
 * other option is the initial value of a listing field — the component used to set those in its own
 * `data()`, where Vue merged them into the mixin's state.
 *
 * @private
 */
export interface UseListingOptions {
    getList: () => void | Promise<void>;
    filters?: () => ListingFilter[];
    page?: number;
    limit?: number;
    total?: number;
    sortBy?: string | null;
    sortDirection?: string;
    naturalSorting?: boolean;
    selection?: Record<string, any>;
    term?: string;
    disableRouteParams?: boolean;
    searchConfigEntity?: string | null;
    entitySearchable?: boolean;
    freshSearchTerm?: boolean;
    storeKey?: string;
    filterCriteria?: any[];
}

/** @private */
export interface UseListingReturn {
    page: Ref<number>;
    limit: Ref<number>;
    total: Ref<number>;
    sortBy: Ref<string | null>;
    sortDirection: Ref<string>;
    naturalSorting: Ref<boolean>;
    selection: Ref<Record<string, any>>;
    term: Ref<string | undefined>;
    disableRouteParams: Ref<boolean>;
    searchConfigEntity: Ref<string | null>;
    entitySearchable: Ref<boolean>;
    freshSearchTerm: Ref<boolean>;
    previousRouteName: Ref<string>;
    storeKey: Ref<string | undefined>;
    filterCriteria: Ref<any[]>;
    maxPage: ComputedRef<number>;
    routeName: ComputedRef<unknown>;
    selectionArray: ComputedRef<any[]>;
    selectionCount: ComputedRef<number>;
    searchRankingFields: ComputedRef<unknown>;
    currentSortBy: ComputedRef<string | null>;
    updateData: (customData: ListingParams) => void;
    updateRoute: (customQuery: ListingParams, queryExtension?: Record<string, unknown>) => void;
    resetListing: () => void;
    getMainListingParams: () => ListingParams;
    updateSelection: (selection: Record<string, any>) => void;
    onPageChange: (opts: { page: number; limit: number }) => void;
    onSearch: (value: string | undefined) => void;
    onSwitchFilter: (filter: any, filterIndex: number) => void;
    onSort: (sorting: { sortBy: string; sortDirection: string }) => void;
    onSortColumn: (column: { dataIndex: string; naturalSorting: boolean }) => void;
    onRefresh: () => void;
    isValidTerm: (term: string) => boolean;
    addQueryScores: (term: string, originalCriteria: Criteria) => Promise<Criteria>;
    parseBooleanQueryParams: (query: LocationQuery) => void;
    updateCriteria: (criteria: any[]) => void;
}

/**
 * Composable alternative to the `listing` mixin: owns the pagination, sorting, search and selection
 * state of a list page, keeps it in sync with the route, and calls back into the component to load the
 * actual data. The mixin stays in place for Options API components.
 *
 * The mixin's `created()` loaded the first page before the component rendered. Here the initial load
 * runs `onMounted`, because `getList` is a callback the caller can only close over once this call has
 * returned. A list page that relied on the load happening before the first render has to be verified.
 *
 * Keep this and `src/app/mixin/listing.mixin.ts` in sync — change both together.
 *
 * @private
 */
export default function useListing(options: UseListingOptions): UseListingReturn {
    const route = useRoute();
    const router = useRouter();

    const page = ref(options.page ?? 1);
    const limit = ref(options.limit ?? 25);
    const total = ref(options.total ?? 0);
    const sortBy = ref<string | null>(options.sortBy ?? null);
    const sortDirection = ref(options.sortDirection ?? 'ASC');
    const naturalSorting = ref(options.naturalSorting ?? false);
    const selection = ref<Record<string, any>>(options.selection ?? []);
    const term = ref<string | undefined>(options.term);
    const disableRouteParams = ref(options.disableRouteParams ?? false);
    const searchConfigEntity = ref<string | null>(options.searchConfigEntity ?? null);
    const entitySearchable = ref(options.entitySearchable ?? true);
    const freshSearchTerm = ref(options.freshSearchTerm ?? false);
    const previousRouteName = ref('');
    const storeKey = ref(options.storeKey);
    const filterCriteria = ref<any[]>(options.filterCriteria ?? []);

    // Resolved per call instead of once: the mixin injected the service, so it was never read before
    // the container had it.
    function searchRankingService(): any {
        return Shopware.Service('searchRankingService');
    }

    const maxPage = computed(() => Math.ceil(total.value / limit.value));

    const routeName = computed(() => route.name);

    const selectionArray = computed(() => Object.values(selection.value));

    const selectionCount = computed(() => selectionArray.value.length);

    const filters = computed(() => options.filters?.() ?? []);

    const searchRankingFields = computed(() => {
        if (!searchConfigEntity.value) {
            return {};
        }

        return searchRankingService().getSearchFieldsByEntity(searchConfigEntity.value);
    });

    const currentSortBy = computed(() => (freshSearchTerm.value ? null : sortBy.value));

    function updateData(customData: ListingParams): void {
        page.value = parseInt(customData.page as string, 10) || page.value;
        limit.value = parseInt(customData.limit as string, 10) || limit.value;
        term.value = (customData.term as string | undefined) ?? term.value;
        sortBy.value = (customData.sortBy as string) || sortBy.value;
        sortDirection.value = (customData.sortDirection as string) || sortDirection.value;
        naturalSorting.value = (customData.naturalSorting as boolean) || naturalSorting.value;
    }

    function updateRoute(customQuery: ListingParams, queryExtension: Record<string, unknown> = {}): void {
        const query = customQuery || route.query;
        const routeQuery = route.query;

        const targetRoute = {
            name: route.name,
            params: route.params,
            query: {
                limit: query.limit || limit.value,
                page: query.page || page.value,
                term: query.term || term.value,
                sortBy: query.sortBy || sortBy.value,
                sortDirection: query.sortDirection || sortDirection.value,
                naturalSorting: query.naturalSorting || naturalSorting.value,
                ...queryExtension,
            },
        };

        // Writing the initial state into an empty query must not add a history entry.
        if (Shopware.Utils.types.isEmpty(routeQuery)) {
            void router.replace(targetRoute as unknown as RouteLocationNamedRaw);
        } else {
            void router.push(targetRoute as unknown as RouteLocationNamedRaw);
        }
    }

    function resetListing(): void {
        page.value = 1;
        term.value = undefined;

        updateRoute({
            page: page.value,
            term: term.value,
        });
    }

    function getMainListingParams(): ListingParams {
        if (disableRouteParams.value) {
            return {
                limit: limit.value,
                page: page.value,
                term: term.value,
                sortBy: sortBy.value,
                sortDirection: sortDirection.value,
                naturalSorting: naturalSorting.value,
            };
        }

        const query = route.query;

        return {
            limit: query.limit,
            page: query.page,
            term: query.term,
            sortBy: query.sortBy || sortBy.value,
            sortDirection: query.sortDirection || sortDirection.value,
            naturalSorting: query.naturalSorting || naturalSorting.value,
        };
    }

    function updateSelection(newSelection: Record<string, any>): void {
        selection.value = newSelection;
    }

    function onPageChange(opts: { page: number; limit: number }): void {
        page.value = opts.page;
        limit.value = opts.limit;

        if (disableRouteParams.value) {
            void options.getList();
            return;
        }

        updateRoute({ page: page.value });
    }

    function onSearch(value: string | undefined): void {
        term.value = value;

        if (disableRouteParams.value) {
            page.value = 1;
            void options.getList();
            return;
        }

        updateRoute({ term: term.value, page: 1 });
    }

    function onSwitchFilter(filter: any, filterIndex: number): void {
        filters.value[filterIndex].active = !filters.value[filterIndex].active;

        page.value = 1;
    }

    function onSort({
        sortBy: newSortBy,
        sortDirection: newSortDirection,
    }: {
        sortBy: string;
        sortDirection: string;
    }): void {
        if (disableRouteParams.value) {
            updateData({ sortBy: newSortBy, sortDirection: newSortDirection });
        } else {
            updateRoute({ sortBy: newSortBy, sortDirection: newSortDirection });
        }

        void options.getList();
    }

    function onSortColumn(column: { dataIndex: string; naturalSorting: boolean }): void {
        if (disableRouteParams.value) {
            if (sortBy.value === column.dataIndex) {
                sortDirection.value = sortDirection.value === 'ASC' ? 'DESC' : 'ASC';
            } else {
                sortDirection.value = 'ASC';
                sortBy.value = column.dataIndex;
            }

            void options.getList();
            return;
        }

        if (sortBy.value === column.dataIndex) {
            updateRoute({ sortDirection: sortDirection.value === 'ASC' ? 'DESC' : 'ASC' });
        } else {
            naturalSorting.value = column.naturalSorting;
            updateRoute({
                sortBy: column.dataIndex,
                sortDirection: 'ASC',
                naturalSorting: column.naturalSorting,
            });
        }
    }

    function onRefresh(): void {
        void options.getList();
    }

    function isValidTerm(searchTerm: string): boolean {
        return searchRankingService().isValidTerm(searchTerm);
    }

    async function addQueryScores(searchTerm: string, originalCriteria: Criteria): Promise<Criteria> {
        entitySearchable.value = true;

        if (!searchConfigEntity.value || !isValidTerm(searchTerm)) {
            return originalCriteria;
        }

        const rankingFields = (await searchRankingService().getSearchFieldsByEntity(searchConfigEntity.value)) as Record<
            string,
            unknown
        > | null;

        if (!rankingFields || Object.keys(rankingFields).length < 1) {
            entitySearchable.value = false;
            return originalCriteria;
        }

        return searchRankingService().buildSearchQueriesForEntity(rankingFields, searchTerm, originalCriteria);
    }

    /**
     * Parses all string representations of boolean values to actual boolean values.
     * Only works on root level of the query object.
     */
    function parseBooleanQueryParams(query: LocationQuery): void {
        Object.keys(query).forEach((key) => {
            if (String(query[key]).toLowerCase() === 'true') {
                // @ts-expect-error - the parsed value intentionally leaves the LocationQuery type
                query[key] = true;
            } else if (String(query[key]).toLowerCase() === 'false') {
                // @ts-expect-error - the parsed value intentionally leaves the LocationQuery type
                query[key] = false;
            }
        });
    }

    /**
     * Update filter criteria and reset pagination to page 1.
     * This method is called when filters are changed via sw-sidebar-filter-panel.
     */
    function updateCriteria(criteria: any[]): void {
        page.value = 1;
        filterCriteria.value = criteria;

        if (disableRouteParams.value) {
            void options.getList();
            return;
        }

        updateRoute({ page: 1 });
    }

    // `router.currentRoute` holds a new location object per navigation, which is what the mixin's
    // `$route` watcher compared; the `useRoute()` proxy keeps its identity and would never differ.
    watch(router.currentRoute, (newRoute, oldRoute) => {
        if (disableRouteParams.value || oldRoute.name !== newRoute.name) {
            return;
        }

        const query = route.query;

        if (Shopware.Utils.types.isEmpty(query)) {
            resetListing();
        }

        parseBooleanQueryParams(query);
        updateData(query);

        if (
            storeKey.value !== undefined &&
            newRoute.query[storeKey.value] !== oldRoute.query[storeKey.value] &&
            filterCriteria.value?.length
        ) {
            filterCriteria.value = [];
            return;
        }

        void options.getList();
    });

    watch(selection, () => {
        Shopware.Store.get('shopwareApps').selectedIds = Object.keys(selection.value);
        Shopware.Store.get('swBulkEdit').selectedIds = Object.keys(selection.value);
    });

    watch(term, (newValue) => {
        freshSearchTerm.value = !!newValue?.length;
    });

    watch(sortBy, () => {
        freshSearchTerm.value = false;
    });

    watch(sortDirection, () => {
        freshSearchTerm.value = false;
    });

    // The mixin's `created()`, one hook later: `options.getList` closes over bindings the caller can
    // only declare below this call.
    onMounted(() => {
        previousRouteName.value = route.name as string;

        if (disableRouteParams.value) {
            void options.getList();
            return;
        }

        const actualQueryParameters: LocationQuery = route.query;

        if (Shopware.Utils.types.isEmpty(actualQueryParameters)) {
            resetListing();
        } else {
            parseBooleanQueryParams(actualQueryParameters);
            updateData(actualQueryParameters);
            void options.getList();
        }
    });

    onBeforeRouteLeave((to) => {
        const targetRouteName = typeof to !== 'string' && 'name' in to ? to.name : undefined;

        // Routes from the `sw-bulk-edit` module are generated under `sw.bulk.edit.*`.
        if (typeof targetRouteName === 'string' && targetRouteName.startsWith('sw.bulk.edit.')) {
            return;
        }

        Shopware.Store.get('shopwareApps').selectedIds = [];
        Shopware.Store.get('swBulkEdit').selectedIds = [];
    });

    return {
        page,
        limit,
        total,
        sortBy,
        sortDirection,
        naturalSorting,
        selection,
        term,
        disableRouteParams,
        searchConfigEntity,
        entitySearchable,
        freshSearchTerm,
        previousRouteName,
        storeKey,
        filterCriteria,
        maxPage,
        routeName,
        selectionArray,
        selectionCount,
        searchRankingFields,
        currentSortBy,
        updateData,
        updateRoute,
        resetListing,
        getMainListingParams,
        updateSelection,
        onPageChange,
        onSearch,
        onSwitchFilter,
        onSort,
        onSortColumn,
        onRefresh,
        isValidTerm,
        addQueryScores,
        parseBooleanQueryParams,
        updateCriteria,
    };
}
