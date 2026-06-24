<template>
    <mt-data-table
        class="sw-meteor-entity-data-table"
        :columns="resolvedColumns"
        :column-changes="columnChanges"
        :data-source="records"
        :current-page="state.page"
        :pagination-limit="state.limit"
        :pagination-total-items="total"
        :is-loading="loading"
        :sort-by="state.sortBy"
        :sort-direction="state.sortDirection"
        :search-value="state.searchTerm"
        :allow-row-selection="showSelections"
        :allow-bulk-delete="showSelections && allowDelete"
        :selected-rows="selectedIds"
        :layout="layout"
        :disable-edit="!allowEdit"
        :disable-delete="!allowDelete"
        :disable-search="disableSearch"
        :disable-settings-table="!showSettings"
        :additional-context-buttons="additionalContextButtons"
        :enable-row-numbering="viewSettings.enableRowNumbering"
        :show-stripes="viewSettings.showStripes"
        :show-outlines="viewSettings.showOutlines"
        :enable-outline-framing="viewSettings.enableOutlineFraming"
        :enable-reload="true"
        :pagination-options="steps"
        :caption="caption"
        @pagination-current-page-change="handlePaginationCurrentPageChange"
        @pagination-limit-change="handlePaginationLimitChange"
        @sort-change="handleSortChange"
        @search-value-change="handleSearchValueChange"
        @reload="reload"
        @selection-change="handleSelectionChange"
        @multiple-selection-change="handleMultipleSelectionChange"
        @open-details="openDetail"
        @context-select="handleContextSelect"
        @item-delete="openDeleteModal"
        @bulk-delete="openBulkDeleteModal"
        @change-enable-row-numbering="setEnableRowNumbering"
        @change-show-stripes="setShowStripes"
        @change-show-outlines="setShowOutlines"
        @change-outline-framing="setEnableOutlineFraming"
    >
        <template v-for="column in columnsWithSlots" :key="column.property" #[`column-${column.property}`]="scope">
            <slot v-if="hasColumnSlot(column.property)" :name="`column-${column.property}`" v-bind="scope" />

            <span v-else class="sw-meteor-entity-data-table__cell">
                <span
                    v-if="hasPreviewSlot(column.property) || getColumnPreviewImage(scope)"
                    class="sw-meteor-entity-data-table__preview"
                >
                    <slot v-if="hasPreviewSlot(column.property)" :name="`preview-${column.property}`" :data="scope.data" />

                    <img
                        v-else
                        class="sw-meteor-entity-data-table__preview-image"
                        :src="getColumnPreviewImage(scope)"
                        :alt="`${getColumnValue(scope)}`"
                    />
                </span>

                <a
                    v-if="scope.data && scope.columnDefinition?.clickable"
                    class="sw-meteor-entity-data-table__column-value sw-meteor-entity-data-table__column-value-link"
                    href="#"
                    @click.prevent="openDetail(scope.data)"
                >
                    {{ getColumnValue(scope) }}
                </a>

                <span v-else class="sw-meteor-entity-data-table__column-value">
                    {{ getColumnValue(scope) }}
                </span>
            </span>
        </template>

        <template v-if="hasEmptyStateSlot" #empty-state>
            <slot name="empty-state" v-bind="emptyStateContext" />
        </template>
    </mt-data-table>

    <sw-meteor-entity-data-table-delete-modal
        v-if="itemToDelete"
        :item="itemToDelete"
        :is-deleting="isDeleting"
        :title-text="$t('global.default.warning')"
        :confirm-text="$t('global.entity-components.deleteMessage')"
        :cancel-text="$t('global.default.cancel')"
        :delete-text="$t('global.default.delete')"
        @close="closeDeleteModal"
        @confirm="confirmDelete"
    >
        <template v-if="$slots['delete-confirm-text']" #delete-confirm-text="{ item }">
            <slot name="delete-confirm-text" :item="item" />
        </template>

        <template v-if="$slots['delete-modal-footer']" #delete-modal-footer="scope">
            <slot name="delete-modal-footer" v-bind="scope" />
        </template>

        <template v-if="$slots['delete-modal-cancel']" #delete-modal-cancel="scope">
            <slot name="delete-modal-cancel" v-bind="scope" />
        </template>

        <template v-if="$slots['delete-modal-delete-item']" #delete-modal-delete-item="scope">
            <slot name="delete-modal-delete-item" v-bind="scope" />
        </template>
    </sw-meteor-entity-data-table-delete-modal>

    <sw-meteor-entity-data-table-bulk-delete-modal
        v-if="bulkDeleteIds.length > 0"
        :selection-count="bulkDeleteIds.length"
        :is-deleting="isBulkDeleting"
        :title-text="$t('global.default.warning')"
        :confirm-text="$t('global.entity-components.deleteMessage', { count: bulkDeleteIds.length }, bulkDeleteIds.length)"
        :cancel-text="$t('global.default.cancel')"
        :delete-text="$t('global.default.delete')"
        @close="closeBulkDeleteModal"
        @confirm="confirmBulkDelete"
    >
        <template v-if="$slots['bulk-modal-delete-confirm-text']" #bulk-modal-delete-confirm-text="{ selectionCount }">
            <slot name="bulk-modal-delete-confirm-text" :selection-count="selectionCount" />
        </template>

        <template v-if="$slots['bulk-modal-cancel']" #bulk-modal-cancel="scope">
            <slot name="bulk-modal-cancel" v-bind="scope" />
        </template>

        <template v-if="$slots['bulk-modal-delete-items']" #bulk-modal-delete-items="scope">
            <slot name="bulk-modal-delete-items" v-bind="scope" />
        </template>
    </sw-meteor-entity-data-table-bulk-delete-modal>
</template>

<script lang="ts">
/**
 * @sw-package framework
 */

/* eslint-disable filename-rules/match, sw-deprecation-rules/private-feature-declarations */

import { computed, defineComponent, getCurrentInstance, inject, onMounted, reactive, ref, watch } from 'vue';
import type { PropType } from 'vue';
import type Criteria from 'src/core/data/criteria.data';
import { createExtendableSetup } from 'src/app/adapter/composition-extension-system';
import SwMeteorEntityDataTableBulkDeleteModal from './components/sw-meteor-entity-data-table-bulk-delete-modal';
import SwMeteorEntityDataTableDeleteModal from './components/sw-meteor-entity-data-table-delete-modal';
import { useMeteorEntityTableColumns } from './composables/use-meteor-entity-table-columns';
import { useMeteorEntityTableCriteria } from './composables/use-meteor-entity-table-criteria';
import { useMeteorEntityTableDelete } from './composables/use-meteor-entity-table-delete';
import { useMeteorEntityTableSelection } from './composables/use-meteor-entity-table-selection';
import { useMeteorEntityTableRouteSync } from './composables/use-meteor-entity-table-route-sync';
import { useMeteorEntityTableSlots } from './composables/use-meteor-entity-table-slots';
import { useMeteorEntityTableState } from './composables/use-meteor-entity-table-state';
import type {
    MeteorEntityTableCriteriaTransform,
    MeteorEntityTableCriteriaResolver,
    MeteorEntityTableColumnChanges,
    MeteorEntityTableColumnDefinition,
    MeteorEntityTableEmptyStateContext,
    MeteorEntityTableLoadSuccessPayload,
    MeteorEntityTableRecord,
    MeteorEntityTableRepository,
    MeteorEntityTableRoute,
    MeteorEntityTableRouteQuery,
    MeteorEntityTableRouteQueryKeys,
    MeteorEntityTableRouter,
} from './sw-meteor-entity-data-table.types';
import { getStateSnapshot } from './sw-meteor-entity-data-table.utils';
import './sw-meteor-entity-data-table.types';

type SwMeteorEntityDataTableViewSettings = {
    enableRowNumbering: boolean;
    showStripes: boolean;
    showOutlines: boolean;
    enableOutlineFraming: boolean;
};

type SwMeteorEntityDataTableRepositoryFactory = {
    create: (entityName: string) => MeteorEntityTableRepository;
};

type SwMeteorEntityDataTableProps = {
    entity?: string | null;
    repository?: MeteorEntityTableRepository | null;
    columns: MeteorEntityTableColumnDefinition[];
    // Controlled input: parent-owned base criteria used for table loading.
    criteria?: Criteria | null;
    criteriaTransform?: MeteorEntityTableCriteriaTransform | null;
    criteriaResolver?: MeteorEntityTableCriteriaResolver | null;
    resetPageOnCriteriaChange: boolean;
    // Controlled input: parent-owned search value bridged through update:searchTerm/search-term-change.
    searchTerm?: string | null;
    syncRouteQuery: boolean;
    routeQueryKeys: Partial<MeteorEntityTableRouteQueryKeys>;
    reloadOnLanguageChange: boolean;
    context?: unknown;
    detailRoute?: string | null;
    // Controlled defaults: route fallback values and table state defaults for parent-owned list pages.
    defaultPage?: number | null;
    defaultLimit?: number | null;
    defaultSearchTerm?: string | null;
    defaultSortBy?: string | null;
    defaultSortDirection?: 'ASC' | 'DESC' | null;
    defaultNaturalSorting?: boolean | null;
    // Initial uncontrolled state: used when no controlled default or route query value is present.
    initialPage: number;
    initialLimit: number;
    initialSearchTerm: string;
    initialSortBy: string;
    initialSortDirection: 'ASC' | 'DESC';
    initialNaturalSorting: boolean;
    allowEdit: boolean;
    allowDelete: boolean;
    showSelections: boolean;
    showSettings: boolean;
    disableSearch: boolean;
    layout: 'default' | 'full';
    steps: number[];
    caption: string;
    additionalContextButtons: Array<{
        type?: 'default' | 'active' | 'critical';
        label: string;
        key: string;
    }>;
};

type SwMeteorEntityDataTableEmit = {
    (e: 'load-success', payload: MeteorEntityTableLoadSuccessPayload): void;
    (e: 'load-error', error: unknown): void;
    // State notifications for parent-owned page chrome and list orchestration.
    (e: 'loading-change', loading: boolean): void;
    (e: 'total-change', total: number): void;
    (e: 'page-change', payload: { page: number; limit: number }): void;
    (e: 'column-sort', column: MeteorEntityTableColumnDefinition | undefined, direction: 'ASC' | 'DESC'): void;
    (e: 'update:searchTerm', searchTerm: string): void;
    (e: 'search-term-change', searchTerm: string): void;
    (e: 'search-value-change', searchTerm: string): void;
    (e: 'selection-change', selection: Record<string, MeteorEntityTableRecord>, selectionCount: number): void;
    (e: 'selected-ids-change', selectedIds: string[]): void;
    (
        e: 'select-item',
        selection: Record<string, MeteorEntityTableRecord>,
        item: MeteorEntityTableRecord | undefined,
        selected: boolean,
    ): void;
    (e: 'select-all-items', selection: Record<string, MeteorEntityTableRecord>): void;
    (e: 'open-detail', payload: { id: string; record: MeteorEntityTableRecord }): void;
    (e: 'context-select', payload: { key: string; data: MeteorEntityTableRecord }): void;
    (e: 'delete-item-finish', id: string): void;
    (e: 'delete-item-failed', payload: { id: string; errorResponse: unknown }): void;
    (e: 'items-delete-finish'): void;
    (e: 'delete-items-failed', payload: { selectedIds: string[]; errorResponse: unknown }): void;
};

export default defineComponent({
    name: 'sw-meteor-entity-data-table',

    components: {
        SwMeteorEntityDataTableBulkDeleteModal,
        SwMeteorEntityDataTableDeleteModal,
    },

    beforeRouteUpdate(to: MeteorEntityTableRoute, _from: MeteorEntityTableRoute, next: () => void) {
        const syncRouteQueryState = (
            this as { syncRouteQueryState?: (query?: MeteorEntityTableRouteQuery) => Promise<void> }
        ).syncRouteQueryState;

        if (typeof syncRouteQueryState === 'function') {
            void syncRouteQueryState(to.query as MeteorEntityTableRouteQuery);
        }

        next();
    },

    props: {
        entity: {
            type: String,
            default: null,
        },
        repository: {
            type: Object as PropType<MeteorEntityTableRepository | null>,
            default: null,
        },
        columns: {
            type: Array as PropType<MeteorEntityTableColumnDefinition[]>,
            required: true,
        },
        criteria: {
            type: Object as PropType<Criteria | null>,
            default: null,
        },
        criteriaTransform: {
            type: Function as unknown as PropType<MeteorEntityTableCriteriaTransform | null>,
            default: null,
        },
        criteriaResolver: {
            type: Function as unknown as PropType<MeteorEntityTableCriteriaResolver | null>,
            default: null,
        },
        resetPageOnCriteriaChange: {
            type: Boolean,
            default: true,
        },
        searchTerm: {
            type: String as PropType<string | null>,
            default: null,
        },
        syncRouteQuery: {
            type: Boolean,
            default: true,
        },
        routeQueryKeys: {
            type: Object as PropType<Partial<MeteorEntityTableRouteQueryKeys>>,
            default: () => ({}),
        },
        reloadOnLanguageChange: {
            type: Boolean,
            default: true,
        },
        context: {
            type: null as unknown as PropType<unknown>,
            default: undefined,
        },
        detailRoute: {
            type: String,
            default: null,
        },
        defaultPage: {
            type: Number,
            default: null,
        },
        defaultLimit: {
            type: Number,
            default: null,
        },
        defaultSearchTerm: {
            type: String,
            default: null,
        },
        defaultSortBy: {
            type: String,
            default: null,
        },
        defaultSortDirection: {
            type: String as PropType<'ASC' | 'DESC' | null>,
            default: null,
        },
        defaultNaturalSorting: {
            type: Boolean as PropType<boolean | null>,
            default: null,
        },
        initialPage: {
            type: Number,
            default: 1,
        },
        initialLimit: {
            type: Number,
            default: 25,
        },
        initialSearchTerm: {
            type: String,
            default: '',
        },
        initialSortBy: {
            type: String,
            default: '',
        },
        initialSortDirection: {
            type: String as PropType<'ASC' | 'DESC'>,
            default: 'ASC',
        },
        initialNaturalSorting: {
            type: Boolean,
            default: false,
        },
        allowEdit: {
            type: Boolean,
            default: true,
        },
        allowDelete: {
            type: Boolean,
            default: true,
        },
        showSelections: {
            type: Boolean,
            default: false,
        },
        showSettings: {
            type: Boolean,
            default: true,
        },
        disableSearch: {
            type: Boolean,
            default: false,
        },
        layout: {
            type: String as PropType<'default' | 'full'>,
            default: 'full',
        },
        steps: {
            type: Array as PropType<number[]>,
            default: () => [
                10,
                25,
                50,
                75,
                100,
            ],
        },
        caption: {
            type: String,
            default: 'Data table',
        },
        additionalContextButtons: {
            type: Array as PropType<SwMeteorEntityDataTableProps['additionalContextButtons']>,
            default: () => [],
        },
    },

    emits: [
        'load-success',
        'load-error',
        'loading-change',
        'total-change',
        'page-change',
        'column-sort',
        'update:searchTerm',
        'search-term-change',
        'search-value-change',
        'selection-change',
        'selected-ids-change',
        'select-item',
        'select-all-items',
        'open-detail',
        'context-select',
        'delete-item-finish',
        'delete-item-failed',
        'items-delete-finish',
        'delete-items-failed',
    ],

    setup(rawProps, { emit: rawEmit, slots }) {
        const props = rawProps as SwMeteorEntityDataTableProps;
        const emit = rawEmit as SwMeteorEntityDataTableEmit;
        const instance = getCurrentInstance();
        const translator = ref((key: string) => {
            return instance?.appContext.config.globalProperties.$t?.(key) ?? key;
        });
        const repositoryFactory = inject<SwMeteorEntityDataTableRepositoryFactory | null>('repositoryFactory', null);

        const setupState = createExtendableSetup(
            {
                props,
                context: {
                    emit,
                    slots,
                },
                name: 'sw-meteor-entity-data-table',
            },
            () => {
                const { resolvedColumns: resolvedTableColumns } = useMeteorEntityTableColumns(
                    () => props.columns,
                    (key) => translator.value(key),
                );
                const resolvedRepository = computed(() => {
                    if (props.repository) {
                        return props.repository;
                    }

                    if (!props.entity) {
                        return null;
                    }

                    return repositoryFactory?.create(props.entity) ?? null;
                });
                const resolveCriteriaTransform = (): MeteorEntityTableCriteriaTransform | null => {
                    if (props.criteriaTransform) {
                        return props.criteriaTransform;
                    }

                    if (props.criteriaResolver) {
                        return (criteria) => props.criteriaResolver?.(criteria) ?? null;
                    }

                    return null;
                };
                const initialPage = props.defaultPage ?? props.initialPage;
                const initialLimit = props.defaultLimit ?? props.initialLimit;
                const initialSearchTerm = props.searchTerm ?? props.defaultSearchTerm ?? props.initialSearchTerm;
                const initialSortBy = props.defaultSortBy ?? props.initialSortBy;
                const initialSortDirection = props.defaultSortDirection ?? props.initialSortDirection;
                const initialNaturalSorting = props.defaultNaturalSorting ?? props.initialNaturalSorting;

                let buildTableCriteria: () => Promise<Criteria | null> = () => Promise.resolve(null);

                const tableState = useMeteorEntityTableState({
                    getRepository: () => resolvedRepository.value,
                    getContext: () => props.context,
                    emit: (event, payload) => {
                        if (event === 'load-success') {
                            emit('load-success', payload as MeteorEntityTableLoadSuccessPayload);
                            return;
                        }

                        emit('load-error', payload);
                    },
                    buildCriteria: () => buildTableCriteria(),
                    initialPage,
                    initialLimit,
                    initialSearchTerm,
                    initialSortBy,
                    initialSortDirection,
                    initialNaturalSorting,
                });

                ({ buildCriteria: buildTableCriteria } = useMeteorEntityTableCriteria({
                    state: tableState.state,
                    getColumns: () => props.columns,
                    getCriteria: () => props.criteria,
                    getCriteriaTransform: () => resolveCriteriaTransform(),
                    getSearchTerm: () => tableState.state.searchTerm,
                }));

                const tableSelection = useMeteorEntityTableSelection(() => tableState.records.value);
                const tableDelete = useMeteorEntityTableDelete({
                    getRepository: () => resolvedRepository.value,
                    getContext: () => props.context,
                    reload: tableState.reload,
                    getSelectedIds: () => tableSelection.selectedIds.value,
                    setSelectedIds: tableSelection.setSelectedIds,
                    emit,
                });
                const tableSlots = useMeteorEntityTableSlots(() => resolvedTableColumns.value, slots, {
                    resolvePreviewImageFallback: (previewImageFallback) => {
                        return Shopware.Filter.getByName('asset')(previewImageFallback);
                    },
                });
                const hasEmptyStateSlot = computed(() => {
                    return typeof slots['empty-state'] === 'function';
                });
                const columnChanges = reactive<MeteorEntityTableColumnChanges>({});
                const viewSettings = reactive<SwMeteorEntityDataTableViewSettings>({
                    enableRowNumbering: false,
                    showStripes: true,
                    showOutlines: true,
                    enableOutlineFraming: false,
                });
                const emptyStateContext = computed<MeteorEntityTableEmptyStateContext>(() => {
                    return {
                        records: tableState.records.value,
                        total: tableState.total.value,
                        loading: tableState.loading.value,
                        state: getStateSnapshot(tableState.state),
                        searchTerm: tableState.state.searchTerm,
                    };
                });

                const setEnableRowNumbering = (value: boolean) => {
                    viewSettings.enableRowNumbering = value;
                };

                const setShowStripes = (value: boolean) => {
                    viewSettings.showStripes = value;
                };

                const setShowOutlines = (value: boolean) => {
                    viewSettings.showOutlines = value;
                };

                const setEnableOutlineFraming = (value: boolean) => {
                    viewSettings.enableOutlineFraming = value;
                };

                const openRecordDetail = (record: MeteorEntityTableRecord) => {
                    emit('open-detail', {
                        id: record.id,
                        record,
                    });

                    if (props.detailRoute) {
                        void instance?.proxy?.$router?.push({
                            name: props.detailRoute,
                            params: {
                                id: record.id,
                            },
                        });
                    }
                };

                const getColumnForSort = (sortBy: string) => {
                    return props.columns.find((column) => {
                        return [
                            column.property,
                            column.dataIndex,
                            column.sortField,
                        ].includes(sortBy);
                    });
                };

                const handleSelectionChange = ({ id, value }: { id: string; value: boolean }) => {
                    const item = tableState.records.value.find((record) => record.id === id);
                    const ids = value
                        ? [
                              ...tableSelection.selectedIds.value,
                              id,
                          ]
                        : tableSelection.selectedIds.value.filter((selectedId) => selectedId !== id);

                    tableSelection.setSelectedIds(ids);
                    emit('selected-ids-change', tableSelection.selectedIds.value);
                    emit('selection-change', tableSelection.selection.value, tableSelection.selectedIds.value.length);
                    emit('select-item', tableSelection.selection.value, item, value);
                };

                const handleMultipleSelectionChange = ({ selections, value }: { selections: string[]; value: boolean }) => {
                    const ids = value
                        ? Array.from(
                              new Set([
                                  ...tableSelection.selectedIds.value,
                                  ...selections,
                              ]),
                          )
                        : tableSelection.selectedIds.value.filter((selectedId) => !selections.includes(selectedId));

                    tableSelection.setSelectedIds(ids);
                    emit('selected-ids-change', tableSelection.selectedIds.value);
                    emit('selection-change', tableSelection.selection.value, tableSelection.selectedIds.value.length);
                    emit('select-all-items', tableSelection.selection.value);
                };

                const handleContextSelect = (payload: { key: string; data: MeteorEntityTableRecord }) => {
                    emit('context-select', payload);
                };

                const getCurrentRoute = () => {
                    return instance?.proxy?.$route as MeteorEntityTableRoute | undefined;
                };

                const getRouter = () => {
                    return instance?.proxy?.$router as MeteorEntityTableRouter | undefined;
                };

                const routeSync = useMeteorEntityTableRouteSync({
                    state: tableState.state,
                    initialState: {
                        page: initialPage,
                        limit: initialLimit,
                        searchTerm: initialSearchTerm,
                        sortBy: initialSortBy,
                        sortDirection: initialSortDirection,
                        naturalSorting: initialNaturalSorting,
                    },
                    getSyncRouteQuery: () => props.syncRouteQuery,
                    getRouteQueryKeys: () => props.routeQueryKeys,
                    getRoute: getCurrentRoute,
                    getRouter,
                    reload: tableState.reload,
                    emitSearchTermChange: (searchTerm) => {
                        emit('update:searchTerm', searchTerm);
                        emit('search-term-change', searchTerm);
                    },
                });

                const setPage = async (page: number) => {
                    const records = await tableState.setPage(page);
                    routeSync.updateRouteQuery('push');

                    return records;
                };

                const setLimit = async (limit: number) => {
                    const records = await tableState.setLimit(limit);
                    routeSync.updateRouteQuery('push');

                    return records;
                };

                const setSearchTerm = async (searchTerm: string) => {
                    const records = await tableState.setSearchTerm(searchTerm);
                    routeSync.updateRouteQuery('push');

                    return records;
                };

                const setSort = async (sortBy: string, sortDirection: 'ASC' | 'DESC', naturalSorting = false) => {
                    const records = await tableState.setSort(sortBy, sortDirection, naturalSorting);
                    routeSync.updateRouteQuery('push');

                    return records;
                };

                const handlePaginationCurrentPageChange = (page: number) => {
                    void setPage(page);
                    emit('page-change', {
                        page,
                        limit: tableState.state.limit,
                    });
                };

                const handlePaginationLimitChange = (limit: number) => {
                    tableState.state.page = 1;
                    void setLimit(limit);
                    emit('page-change', {
                        page: 1,
                        limit,
                    });
                };

                const handleSortChange = (sortBy: string, sortDirection: 'ASC' | 'DESC') => {
                    const column = getColumnForSort(sortBy);

                    tableState.state.page = 1;
                    void setSort(sortBy, sortDirection, column?.naturalSorting ?? false);
                    emit('column-sort', column, sortDirection);
                };

                const handleSearchValueChange = (searchTerm: string) => {
                    tableState.state.page = 1;
                    void setSearchTerm(searchTerm);
                    routeSync.emitSearchTermBridge(searchTerm);
                    emit('search-value-change', searchTerm);
                };

                onMounted(() => {
                    translator.value = (key: string) => {
                        return instance?.proxy?.$t?.(key) ?? instance?.appContext.config.globalProperties.$t?.(key) ?? key;
                    };

                    routeSync.syncInitialRouteState();

                    void tableState.load().finally(() => {
                        routeSync.markLoaded();
                    });
                });

                watch(tableState.loading, (loading) => {
                    emit('loading-change', loading);
                });

                watch(tableState.total, (total) => {
                    emit('total-change', total);
                });

                watch(tableState.records, () => {
                    const previousSelectedIds = [...tableSelection.selectedIds.value];

                    tableSelection.pruneSelection();

                    if (previousSelectedIds.length !== tableSelection.selectedIds.value.length) {
                        emit('selected-ids-change', tableSelection.selectedIds.value);
                        emit('selection-change', tableSelection.selection.value, tableSelection.selectedIds.value.length);
                    }
                });

                watch(
                    () =>
                        [
                            props.initialPage,
                            props.initialLimit,
                            props.initialSearchTerm,
                            props.initialSortBy,
                            props.initialSortDirection,
                            props.initialNaturalSorting,
                        ] as const,
                    ([
                        page,
                        limit,
                        searchTerm,
                        sortBy,
                        sortDirection,
                        naturalSorting,
                    ]) => {
                        const controlledSearchTerm = props.searchTerm ?? searchTerm;
                        const changed = routeSync.syncState({
                            page,
                            limit,
                            searchTerm: controlledSearchTerm,
                            sortBy,
                            sortDirection,
                            naturalSorting,
                        });

                        if (changed) {
                            routeSync.markStateChangeFromRoute();
                        }
                    },
                    {
                        flush: 'sync',
                    },
                );

                watch(
                    () =>
                        [
                            props.criteria,
                            props.searchTerm,
                            props.criteriaTransform,
                            props.criteriaResolver,
                        ] as const,
                    (
                        [
                            criteria,
                            searchTerm,
                            criteriaTransform,
                            criteriaResolver,
                        ],
                        [
                            previousCriteria,
                            previousSearchTerm,
                            previousCriteriaTransform,
                            previousCriteriaResolver,
                        ],
                    ) => {
                        if (!routeSync.isLoaded()) {
                            return;
                        }

                        const criteriaChanged = criteria !== previousCriteria;
                        const searchTermChanged = searchTerm !== previousSearchTerm;
                        const criteriaTransformChanged =
                            criteriaTransform !== previousCriteriaTransform || criteriaResolver !== previousCriteriaResolver;

                        if (searchTermChanged && routeSync.shouldSkipSearchTermPropReload(searchTerm)) {
                            return;
                        }

                        if (searchTermChanged) {
                            tableState.state.searchTerm = searchTerm ?? '';
                        }

                        if (
                            (criteriaChanged || searchTermChanged) &&
                            props.resetPageOnCriteriaChange &&
                            !routeSync.shouldSkipCriteriaPageReset()
                        ) {
                            tableState.state.page = 1;
                        }

                        routeSync.clearCriteriaPageResetSkip();

                        if (criteriaChanged || searchTermChanged || criteriaTransformChanged) {
                            void tableState.reload();
                            routeSync.updateRouteQuery('push');
                        }
                    },
                );

                watch(
                    () => (props.syncRouteQuery ? routeSync.getRouteQuerySnapshot() : null),
                    (query) => {
                        void routeSync.syncRouteQueryState(query ?? {});
                    },
                    {
                        deep: true,
                    },
                );

                watch(
                    () => Shopware.Store.get('context')?.api?.languageId,
                    (languageId, previousLanguageId) => {
                        if (!props.reloadOnLanguageChange || !routeSync.isLoaded() || languageId === previousLanguageId) {
                            return;
                        }

                        void tableState.reload();
                    },
                );

                return {
                    public: {
                        records: tableState.records,
                        total: tableState.total,
                        loading: tableState.loading,
                        state: tableState.state,
                        selectedIds: tableSelection.selectedIds,
                        selection: tableSelection.selection,
                        resolvedColumns: resolvedTableColumns,
                        load: tableState.load,
                        reload: tableState.reload,
                        buildCriteria: buildTableCriteria,
                        setPage,
                        setLimit,
                        setSearchTerm,
                        setSort,
                        setSelectedIds: tableSelection.setSelectedIds,
                        openDetail: openRecordDetail,
                    },
                    private: {
                        syncRouteQueryState: routeSync.syncRouteQueryState,
                        rebuildSelection: tableSelection.rebuildSelection,
                        pruneSelection: tableSelection.pruneSelection,
                        columnsWithSlots: tableSlots.columnsWithSlots,
                        hasColumnSlot: tableSlots.hasColumnSlot,
                        hasPreviewSlot: tableSlots.hasPreviewSlot,
                        hasEmptyStateSlot,
                        columnChanges,
                        viewSettings,
                        emptyStateContext,
                        getColumnValue: tableSlots.getColumnValue,
                        getColumnPreviewImage: tableSlots.getColumnPreviewImage,
                        itemToDelete: tableDelete.itemToDelete,
                        isDeleting: tableDelete.isDeleting,
                        bulkDeleteIds: tableDelete.bulkDeleteIds,
                        isBulkDeleting: tableDelete.isBulkDeleting,
                        openDeleteModal: tableDelete.openDeleteModal,
                        closeDeleteModal: tableDelete.closeDeleteModal,
                        confirmDelete: tableDelete.confirmDelete,
                        openBulkDeleteModal: tableDelete.openBulkDeleteModal,
                        closeBulkDeleteModal: tableDelete.closeBulkDeleteModal,
                        confirmBulkDelete: tableDelete.confirmBulkDelete,
                        handlePaginationCurrentPageChange,
                        handlePaginationLimitChange,
                        handleSortChange,
                        handleSearchValueChange,
                        handleSelectionChange,
                        handleMultipleSelectionChange,
                        handleContextSelect,
                        setEnableRowNumbering,
                        setShowStripes,
                        setShowOutlines,
                        setEnableOutlineFraming,
                    },
                };
            },
        );

        return setupState;
    },
});
</script>

<style lang="scss">
/**
 * @sw-package framework
 */

.sw-meteor-entity-data-table.mt-card {
    display: flex;
    flex-direction: column;
    width: 100%;
    max-width: none;
    height: 100%;
    min-height: 0;
    margin: 0;

    .mt-card__toolbar {
        flex: 0 0 auto;
    }

    .mt-card__toolbar:has(.mt-data-table__toolbar:empty) {
        display: none;
    }

    .mt-card__content {
        display: flex;
        flex: 1 1 auto;
        flex-direction: column;
        min-height: 0;
    }

    .mt-data-table__table-wrapper {
        flex: 1 1 auto;
        min-height: 0;
    }

    .mt-data-table__footer-inset {
        flex: 0 0 auto;
    }
}

.sw-meteor-entity-data-table {
    .sw-meteor-entity-data-table__cell {
        display: flex;
        align-items: center;
        gap: 12px;
        min-width: 0;
    }

    .sw-meteor-entity-data-table__preview {
        display: inline-flex;
        flex: 0 0 auto;
        align-items: center;
        justify-content: center;
        width: 34px;
        height: var(--scale-size-24);
        border: 1px solid var(--color-border-secondary-default);
        border-radius: var(--border-radius-xs);
    }

    .sw-meteor-entity-data-table__preview-image {
        max-width: calc(100% - 5px);
        max-height: calc(100% - 5px);
    }

    .sw-meteor-entity-data-table__column-value {
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

}
</style>
