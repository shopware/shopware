<template>
    <sw-block name="sw_meteor_entity_data_table" :data="blockDataScope">
        <div class="sw-meteor-entity-data-table">
            <sw-block name="sw_meteor_entity_data_table_before_table" :data="blockDataScope" />

            <sw-block name="sw_meteor_entity_data_table_table" :data="blockDataScope">
                <mt-data-table
                    class="sw-meteor-entity-data-table__table"
                    :data-source="dataSource"
                    :columns="normalizedColumns"
                    :current-page="page"
                    :pagination-limit="limit"
                    :pagination-options="paginationOptions"
                    :pagination-total-items="totalItems"
                    :sort-by="sortBy"
                    :sort-direction="sortDirection"
                    :search-value="searchTerm"
                    :is-loading="loading"
                    :allow-row-selection="allowRowSelection"
                    :allow-bulk-delete="tableAllowBulkDelete"
                    :allow-bulk-edit="allowBulkEdit"
                    :selected-rows="selectedIds"
                    :disable-row-select="disableRowSelect"
                    :disable-search="disableSearch"
                    :disable-edit="tableDisableEdit"
                    :disable-delete="tableDisableDelete"
                    :disable-settings-table="disableSettingsTable"
                    :enable-reload="enableReload"
                    :column-changes="columnChanges"
                    :additional-context-buttons="tableAdditionalContextButtons"
                    @sort-change="setSort"
                    @pagination-current-page-change="setPage"
                    @pagination-limit-change="setLimit"
                    @search-value-change="onSearchValueChange"
                    @selection-change="onSelectionChange"
                    @multiple-selection-change="onMultipleSelectionChange"
                    @reload="onReload"
                    @open-details="onOpenDetails"
                    @context-select="onContextSelect"
                >
                    <template #toolbar="toolbarScope">
                        <sw-block name="sw_meteor_entity_data_table_toolbar" :data="blockDataScope">
                            <slot name="toolbar" v-bind="toolbarScope || {}"></slot>
                        </sw-block>
                    </template>

                    <template #empty-state="emptyStateScope">
                        <sw-block name="sw_meteor_entity_data_table_empty_state" :data="blockDataScope">
                            <slot name="empty-state" v-bind="emptyStateScope || {}"></slot>
                        </sw-block>
                    </template>
                </mt-data-table>
            </sw-block>

            <sw-block name="sw_meteor_entity_data_table_after_table" :data="blockDataScope" />
        </div>
    </sw-block>
</template>

<script lang="ts">
/**
 * @sw-package framework
 */

import { computed, defineComponent, getCurrentInstance, onMounted, ref, watch } from 'vue';
import type { ComputedRef, PropType, Ref, SetupContext } from 'vue';
import type { Router } from 'vue-router';
import type EntityCollection from '@shopware-ag/meteor-admin-sdk/es/_internals/data/EntityCollection';
import type { ApiContext } from '@shopware-ag/meteor-admin-sdk/es/_internals/data/EntityCollection';
import type { Entity } from '@shopware-ag/meteor-admin-sdk/es/_internals/data/Entity';
import { createExtendableSetup } from 'src/app/adapter/composition-extension-system';
import type CriteriaType from 'src/core/data/criteria.data';
import type Repository from 'src/core/data/repository.data';
import { normalizeSwMeteorEntityDataTableColumns } from './sw-meteor-entity-data-table-column-normalizer';
import type {
    SwMeteorEntityDataTableColumn,
    SwMeteorEntityDataTableColumnSortMetadata,
    SwMeteorEntityDataTableNormalizedColumn,
} from './sw-meteor-entity-data-table.types';

type SwMeteorEntityDataTableEntityName = keyof EntitySchema.Entities;

type SwMeteorEntityDataTableRecord =
    | Entity<SwMeteorEntityDataTableEntityName>
    | {
          id: string;
          [key: string]: unknown;
      };

type SwMeteorEntityDataTableRecords = EntityCollection<SwMeteorEntityDataTableEntityName> | SwMeteorEntityDataTableRecord[];

type SwMeteorEntityDataTableColumnChanges = {
    property?: string;
    position?: number;
    width?: number;
    visible?: boolean;
};

type SwMeteorEntityDataTableAdditionalContextButton = {
    type?: 'default' | 'active' | 'critical';
    label: string;
    key: string;
};

type SwMeteorEntityDataTableProps = {
    repository: Repository<SwMeteorEntityDataTableEntityName>;
    columns: SwMeteorEntityDataTableColumn[];
    criteria?: CriteriaType | null;
    context?: ApiContext | null;
    identifier?: string | null;
    detailRoute?: string | null;
    allowEdit?: boolean;
    allowView?: boolean;
    allowDelete?: boolean;
    allowBulkDelete?: boolean;
    allowBulkEdit?: boolean;
    allowRowSelection?: boolean;
    showActions?: boolean;
    showSettings?: boolean;
    enableReload?: boolean;
    records?: SwMeteorEntityDataTableRecords | null;
    total?: number | null;
    isLoading?: boolean;
    disableDataFetching?: boolean;
    disableSearch?: boolean;
    searchValue?: string;
    paginationOptions?: number[];
    initialPage?: number;
    initialLimit?: number;
    title?: string;
    subtitle?: string;
    caption?: string;
    layout?: 'default' | 'full';
    additionalContextButtons?: SwMeteorEntityDataTableAdditionalContextButton[];
};

type SwMeteorEntityDataTableSortDirection = 'ASC' | 'DESC';

type SwMeteorEntityDataTableContextSelectPayload = {
    key: string;
    data: SwMeteorEntityDataTableRecord;
};

type SelectionChangePayload = {
    id: string;
    value: boolean;
};

type MultipleSelectionChangePayload = {
    selections: string[];
    value: boolean;
};

type SwMeteorEntityDataTableRouter = Pick<Router, 'push'>;

type SwMeteorEntityDataTablePublicApi = {
    dataSource: Ref<SwMeteorEntityDataTableRecords>;
    totalItems: Ref<number>;
    page: Ref<number>;
    limit: Ref<number>;
    sortBy: Ref<string>;
    sortDirection: Ref<SwMeteorEntityDataTableSortDirection>;
    searchTerm: Ref<string>;
    loading: Ref<boolean>;
    selectedIds: Ref<string[]>;
    columnChanges: Ref<Record<string, SwMeteorEntityDataTableColumnChanges>>;
    normalizedColumns: ComputedRef<SwMeteorEntityDataTableNormalizedColumn[]>;
    buildCriteria: () => CriteriaType;
    loadData: () => Promise<void>;
    setSort: (property: string, direction: SwMeteorEntityDataTableSortDirection) => Promise<void> | void;
    setPage: (page: number) => Promise<void> | void;
    setLimit: (limit: number) => Promise<void> | void;
    setSelectedIds: (selectedIds: string[]) => void;
    deleteItem: (id: string) => Promise<void>;
    deleteItems: (ids: string[]) => Promise<void>;
};

type SwMeteorEntityDataTableBlockDataScope = {
    [Property in keyof SwMeteorEntityDataTablePublicApi]: SwMeteorEntityDataTablePublicApi[Property] extends Ref<
        infer TValue
    >
        ? TValue
        : SwMeteorEntityDataTablePublicApi[Property];
};

type SwMeteorEntityDataTablePrivateApi = {
    blockDataScope: ComputedRef<SwMeteorEntityDataTableBlockDataScope>;
    disableRowSelect: Ref<string[]>;
    tableAllowBulkDelete: ComputedRef<boolean>;
    tableDisableEdit: ComputedRef<boolean>;
    tableDisableDelete: ComputedRef<boolean>;
    tableAdditionalContextButtons: ComputedRef<SwMeteorEntityDataTableAdditionalContextButton[]>;
    disableSettingsTable: ComputedRef<boolean>;
    columnSortMetadataByProperty: ComputedRef<Record<string, SwMeteorEntityDataTableColumnSortMetadata>>;
    onSelectionChange: (payload: SelectionChangePayload) => void;
    onMultipleSelectionChange: (payload: MultipleSelectionChangePayload) => void;
    onSearchValueChange: (term: string) => void;
    onOpenDetails: (record: SwMeteorEntityDataTableRecord) => void;
    onContextSelect: (payload: SwMeteorEntityDataTableContextSelectPayload) => void;
    onReload: () => void;
};

declare global {
    interface ComponentPublicApiMapping {
        'sw-meteor-entity-data-table': SwMeteorEntityDataTablePublicApi;
    }
}

type SetupProps = SwMeteorEntityDataTableProps & Record<string, unknown>;

const { Criteria } = Shopware.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default defineComponent({
    name: 'SwMeteorEntityDataTable',

    props: {
        repository: {
            type: Object as PropType<SwMeteorEntityDataTableProps['repository']>,
            required: true,
        },

        columns: {
            type: Array as PropType<SwMeteorEntityDataTableColumn[]>,
            required: true,
        },

        criteria: {
            type: Object as PropType<SwMeteorEntityDataTableProps['criteria']>,
            required: false,
            default: null,
        },

        context: {
            type: Object as PropType<SwMeteorEntityDataTableProps['context']>,
            required: false,
            default: null,
        },

        identifier: {
            type: String,
            required: false,
            default: null,
        },

        detailRoute: {
            type: String,
            required: false,
            default: null,
        },

        allowEdit: {
            type: Boolean,
            required: false,
            default: false,
        },

        allowView: {
            type: Boolean,
            required: false,
            default: false,
        },

        allowDelete: {
            type: Boolean,
            required: false,
            default: false,
        },

        allowBulkDelete: {
            type: Boolean,
            required: false,
            default: false,
        },

        allowBulkEdit: {
            type: Boolean,
            required: false,
            default: false,
        },

        allowRowSelection: {
            type: Boolean,
            required: false,
            default: false,
        },

        showActions: {
            type: Boolean,
            required: false,
            default: true,
        },

        showSettings: {
            type: Boolean,
            required: false,
            default: true,
        },

        enableReload: {
            type: Boolean,
            required: false,
            default: false,
        },

        records: {
            type: Array as PropType<SwMeteorEntityDataTableRecords | null>,
            required: false,
            default: null,
        },

        total: {
            type: Number as PropType<SwMeteorEntityDataTableProps['total']>,
            required: false,
            default: null,
        },

        isLoading: {
            type: Boolean,
            required: false,
            default: false,
        },

        disableDataFetching: {
            type: Boolean,
            required: false,
            default: false,
        },

        disableSearch: {
            type: Boolean,
            required: false,
            default: false,
        },

        searchValue: {
            type: String,
            required: false,
            default: '',
        },

        paginationOptions: {
            type: Array as PropType<number[]>,
            required: false,
            default() {
                return [
                    5,
                    10,
                    25,
                    50,
                ];
            },
        },

        initialPage: {
            type: Number,
            required: false,
            default: 1,
        },

        initialLimit: {
            type: Number,
            required: false,
            default: 25,
        },

        title: {
            type: String,
            required: false,
            default: '',
        },

        subtitle: {
            type: String,
            required: false,
            default: '',
        },

        caption: {
            type: String,
            required: false,
            default: '',
        },

        layout: {
            type: String as PropType<'default' | 'full'>,
            required: false,
            default: 'default',
            validator(value: string): boolean {
                return [
                    'default',
                    'full',
                ].includes(value);
            },
        },

        additionalContextButtons: {
            type: Array as PropType<SwMeteorEntityDataTableAdditionalContextButton[]>,
            required: false,
            default() {
                return [];
            },
        },
    },

    emits: [
        'update-records',
        'load-failed',
        'sort-change',
        'page-change',
        'search-change',
        'selection-change',
        'open-details',
        'context-select',
        'delete-finish',
        'delete-failed',
        'bulk-delete-finish',
        'bulk-delete-failed',
    ],

    setup(rawProps, context: SetupContext) {
        const props = rawProps as SetupProps;

        return createExtendableSetup<
            SetupProps,
            SetupContext,
            'sw-meteor-entity-data-table',
            SwMeteorEntityDataTablePublicApi,
            SwMeteorEntityDataTablePrivateApi
        >(
            {
                name: 'sw-meteor-entity-data-table',
                props,
                context,
            },
            (setupProps, setupContext) => {
                const dataSource: Ref<SwMeteorEntityDataTableRecords> = ref(setupProps.records ?? []);
                const totalItems = ref(resolveTotal(setupProps.records, setupProps.total));
                const page = ref(setupProps.initialPage ?? 1);
                const limit = ref(setupProps.initialLimit ?? 25);
                const sortBy = ref('');
                const sortDirection = ref<SwMeteorEntityDataTableSortDirection>('ASC');
                const searchTerm = ref(setupProps.searchValue ?? '');
                const loading = ref(setupProps.records !== null ? setupProps.isLoading === true : false);
                const selectedIds = ref<string[]>([]);
                const columnChanges = ref({});
                const disableRowSelect = ref<string[]>([]);
                const instanceRouter = getCurrentInstance()?.proxy?.$router as SwMeteorEntityDataTableRouter | undefined;
                const isControlledDataMode = computed(() => setupProps.records !== null);
                const columnNormalization = computed(() => {
                    return normalizeSwMeteorEntityDataTableColumns(setupProps.columns);
                });
                void columnNormalization.value;

                const normalizedColumns = computed(() => columnNormalization.value.columns);
                const columnSortMetadataByProperty = computed(() => columnNormalization.value.sortMetadataByProperty);

                function buildCriteria() {
                    const criteria = setupProps.criteria
                        ? Criteria.fromCriteria(setupProps.criteria)
                        : new Criteria(page.value, limit.value);

                    criteria.setPage(page.value);
                    criteria.setLimit(limit.value);
                    criteria.setTerm(searchTerm.value);
                    criteria.resetSorting();

                    const sortMetadata = getActiveSortMetadata();

                    getActiveSortingFields().forEach((field) => {
                        criteria.addSorting(
                            Criteria.sort(field, sortDirection.value, sortMetadata?.naturalSorting ?? false),
                        );
                    });

                    return criteria;
                }

                async function loadData(): Promise<void> {
                    if (isControlledDataMode.value || setupProps.disableDataFetching) {
                        return;
                    }

                    loading.value = true;

                    try {
                        const searchContext = (setupProps.context ?? Shopware.Context.api) as typeof Shopware.Context.api;
                        const result = await setupProps.repository.search(
                            buildCriteria(),
                            searchContext,
                        );

                        dataSource.value = result;
                        totalItems.value = resolveTotal(result, result.total);

                        setupContext.emit('update-records', result);
                    } catch (errorResponse) {
                        setupContext.emit('load-failed', errorResponse);
                    } finally {
                        loading.value = false;
                    }
                }

                function getActiveSortMetadata(): SwMeteorEntityDataTableColumnSortMetadata | null {
                    if (!sortBy.value) {
                        return null;
                    }

                    return columnSortMetadataByProperty.value[sortBy.value] ?? null;
                }

                function getActiveSortingFields(): string[] {
                    const sortMetadata = getActiveSortMetadata();
                    const dataIndex = sortMetadata?.dataIndex ?? sortBy.value;

                    return dataIndex
                        .split(',')
                        .map((field) => field.trim())
                        .filter((field) => field.length > 0);
                }

                function emitPageChange(): void {
                    setupContext.emit('page-change', {
                        page: page.value,
                        limit: limit.value,
                    });
                }

                function resetPage(): void {
                    if (page.value === 1) {
                        return;
                    }

                    page.value = 1;
                    emitPageChange();
                }

                function setSort(
                    property: string,
                    direction: SwMeteorEntityDataTableSortDirection,
                ): Promise<void> | void {
                    const sortMetadata = columnSortMetadataByProperty.value[property];

                    sortBy.value = property;
                    sortDirection.value = direction;
                    resetPage();

                    setupContext.emit('sort-change', {
                        property,
                        dataIndex: sortMetadata?.dataIndex ?? property,
                        direction,
                        naturalSorting: sortMetadata?.naturalSorting ?? false,
                    });

                    if (sortMetadata?.useCustomSort) {
                        return;
                    }

                    return loadData();
                }

                function setPage(nextPage: number): Promise<void> {
                    page.value = nextPage;
                    emitPageChange();

                    return loadData();
                }

                function setLimit(nextLimit: number): Promise<void> {
                    limit.value = nextLimit;
                    page.value = 1;
                    emitPageChange();

                    return loadData();
                }

                function setSelectedIds(nextSelectedIds: string[]): void {
                    selectedIds.value = [
                        ...nextSelectedIds,
                    ];

                    setupContext.emit('selection-change', selectedIds.value);
                }

                function deleteItem(): Promise<void> {
                    return Promise.resolve();
                }

                function deleteItems(): Promise<void> {
                    return Promise.resolve();
                }

                function onSelectionChange(payload: SelectionChangePayload): void {
                    if (payload.value) {
                        if (selectedIds.value.includes(payload.id)) {
                            return;
                        }

                        setSelectedIds([
                            ...selectedIds.value,
                            payload.id,
                        ]);
                        return;
                    }

                    setSelectedIds(selectedIds.value.filter((id) => id !== payload.id));
                }

                function onMultipleSelectionChange(payload: MultipleSelectionChangePayload): void {
                    if (payload.value) {
                        const nextSelectedIds = [
                            ...selectedIds.value,
                            ...payload.selections.filter((id) => !selectedIds.value.includes(id)),
                        ];

                        setSelectedIds(nextSelectedIds);
                        return;
                    }

                    setSelectedIds(selectedIds.value.filter((id) => !payload.selections.includes(id)));
                }

                function onSearchValueChange(term: string): void {
                    searchTerm.value = term;
                    resetPage();

                    setupContext.emit('search-change', term);

                    void loadData();
                }

                function onOpenDetails(record: SwMeteorEntityDataTableRecord): void {
                    const openDetailsPayload = {
                        id: record.id,
                    };

                    if (setupProps.detailRoute) {
                        void getRouter(instanceRouter)?.push({
                            name: setupProps.detailRoute,
                            params: {
                                id: record.id,
                            },
                        });
                    }

                    setupContext.emit('open-details', openDetailsPayload);
                }

                function onContextSelect(payload: SwMeteorEntityDataTableContextSelectPayload): void {
                    setupContext.emit('context-select', payload);
                }

                function onReload(): void {
                    void loadData();
                }

                const tableAllowBulkDelete = computed(() => {
                    return setupProps.allowBulkDelete === true && setupProps.allowDelete === true;
                });
                const tableDisableEdit = computed(() => {
                    return setupProps.showActions === false || !(setupProps.allowEdit === true || setupProps.allowView === true);
                });
                const tableDisableDelete = computed(() => {
                    return setupProps.showActions === false || setupProps.allowDelete !== true;
                });
                const tableAdditionalContextButtons = computed(() => {
                    if (setupProps.showActions === false) {
                        return [];
                    }

                    return setupProps.additionalContextButtons ?? [];
                });
                const disableSettingsTable = computed(() => !setupProps.showSettings);

                const blockDataScope = computed(() => ({
                    dataSource: dataSource.value,
                    totalItems: totalItems.value,
                    page: page.value,
                    limit: limit.value,
                    sortBy: sortBy.value,
                    sortDirection: sortDirection.value,
                    searchTerm: searchTerm.value,
                    loading: loading.value,
                    selectedIds: selectedIds.value,
                    columnChanges: columnChanges.value,
                    normalizedColumns: normalizedColumns.value,
                    buildCriteria,
                    loadData,
                    setSort,
                    setPage,
                    setLimit,
                    setSelectedIds,
                    deleteItem,
                    deleteItems,
                }));

                watch(
                    () => setupProps.records,
                    (nextRecords) => {
                        if (!isControlledDataMode.value) {
                            void loadData();
                            return;
                        }

                        dataSource.value = nextRecords ?? [];
                        totalItems.value = resolveTotal(nextRecords, setupProps.total);
                    },
                );

                watch(
                    () => setupProps.total,
                    (nextTotal) => {
                        if (!isControlledDataMode.value) {
                            return;
                        }

                        totalItems.value = resolveTotal(dataSource.value, nextTotal);
                    },
                );

                watch(
                    () => setupProps.isLoading,
                    (nextIsLoading) => {
                        if (!isControlledDataMode.value) {
                            return;
                        }

                        loading.value = nextIsLoading === true;
                    },
                );

                watch(
                    () => setupProps.searchValue,
                    (nextSearchValue) => {
                        searchTerm.value = nextSearchValue ?? '';

                        void loadData();
                    },
                );

                watch(
                    () => setupProps.criteria,
                    () => {
                        void loadData();
                    },
                );

                watch(
                    () => setupProps.context,
                    () => {
                        void loadData();
                    },
                );

                watch(
                    () => setupProps.disableDataFetching,
                    (nextDisableDataFetching) => {
                        if (nextDisableDataFetching) {
                            return;
                        }

                        void loadData();
                    },
                );

                onMounted(() => {
                    void loadData();
                });

                return {
                    public: {
                        dataSource,
                        totalItems,
                        page,
                        limit,
                        sortBy,
                        sortDirection,
                        searchTerm,
                        loading,
                        selectedIds,
                        columnChanges,
                        normalizedColumns,
                        buildCriteria,
                        loadData,
                        setSort,
                        setPage,
                        setLimit,
                        setSelectedIds,
                        deleteItem,
                        deleteItems,
                    },
                    private: {
                        blockDataScope,
                        disableRowSelect,
                        tableAllowBulkDelete,
                        tableDisableEdit,
                        tableDisableDelete,
                        tableAdditionalContextButtons,
                        disableSettingsTable,
                        columnSortMetadataByProperty,
                        onSelectionChange,
                        onMultipleSelectionChange,
                        onSearchValueChange,
                        onOpenDetails,
                        onContextSelect,
                        onReload,
                    },
                };
            },
        );
    },
});

function resolveTotal(records: SwMeteorEntityDataTableRecords | null | undefined, total: number | null | undefined): number {
    if (typeof total === 'number') {
        return total;
    }

    if (records && 'total' in records && typeof records.total === 'number') {
        return records.total;
    }

    return records?.length ?? 0;
}

function getRouter(instanceRouter: SwMeteorEntityDataTableRouter | undefined): SwMeteorEntityDataTableRouter | undefined {
    const shopwareApplication = Shopware.Application as unknown as {
        view?: {
            router?: SwMeteorEntityDataTableRouter;
        };
    };

    return instanceRouter ?? shopwareApplication.view?.router;
}
</script>

<style lang="scss">
.sw-meteor-entity-data-table {
    width: 100%;
}
</style>
