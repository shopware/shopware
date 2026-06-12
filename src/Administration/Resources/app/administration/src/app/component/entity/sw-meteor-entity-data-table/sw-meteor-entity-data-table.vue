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
                    :pagination-total-items="totalItems"
                    :sort-by="sortBy"
                    :sort-direction="sortDirection"
                    :is-loading="isLoading"
                    :allow-row-selection="allowRowSelection"
                    :allow-bulk-delete="allowBulkDelete"
                    :allow-bulk-edit="allowBulkEdit"
                    :selected-rows="selectedIds"
                    :disable-row-select="disableRowSelect"
                    :disable-search="disableSearch"
                    :disable-edit="!allowEdit"
                    :disable-delete="!allowDelete"
                    :disable-settings-table="disableSettingsTable"
                    :enable-reload="enableReload"
                    :column-changes="columnChanges"
                    :additional-context-buttons="additionalContextButtons"
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

import { computed, defineComponent, ref, watch } from 'vue';
import type { ComputedRef, PropType, Ref, SetupContext } from 'vue';
import type EntityCollection from '@shopware-ag/meteor-admin-sdk/es/_internals/data/EntityCollection';
import type { ApiContext } from '@shopware-ag/meteor-admin-sdk/es/_internals/data/EntityCollection';
import type { Entity } from '@shopware-ag/meteor-admin-sdk/es/_internals/data/Entity';
import { createExtendableSetup } from 'src/app/adapter/composition-extension-system';
import type CriteriaType from 'src/core/data/criteria.data';
import type Repository from 'src/core/data/repository.data';

type SwMeteorEntityDataTableEntityName = keyof EntitySchema.Entities;

type SwMeteorEntityDataTableRecord =
    | Entity<SwMeteorEntityDataTableEntityName>
    | {
          id: string;
          [key: string]: unknown;
      };

type SwMeteorEntityDataTableRecords = EntityCollection<SwMeteorEntityDataTableEntityName> | SwMeteorEntityDataTableRecord[];

type SwMeteorEntityDataTableColumn = {
    label: string;
    property: string;
    renderer: 'text' | 'number' | 'price' | 'badge';
    position: number;
    sortable?: boolean;
    width?: number;
    allowResize?: boolean;
    cellWrap?: 'nowrap' | 'normal';
    visible?: boolean;
    clickable?: boolean;
    previewImage?: string;
    rendererOptions?: unknown;
};

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

type SwMeteorEntityDataTablePublicApi = {
    dataSource: Ref<SwMeteorEntityDataTableRecords>;
    totalItems: Ref<number>;
    page: Ref<number>;
    limit: Ref<number>;
    sortBy: Ref<string>;
    sortDirection: Ref<SwMeteorEntityDataTableSortDirection>;
    selectedIds: Ref<string[]>;
    columnChanges: Ref<Record<string, SwMeteorEntityDataTableColumnChanges>>;
    normalizedColumns: ComputedRef<SwMeteorEntityDataTableColumn[]>;
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
    disableSettingsTable: ComputedRef<boolean>;
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
            default: false,
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
                const selectedIds = ref<string[]>([]);
                const columnChanges = ref({});
                const disableRowSelect = ref<string[]>([]);
                const normalizedColumns = computed<SwMeteorEntityDataTableColumn[]>(() => {
                    const configuredColumns: unknown = setupProps.columns;

                    if (!Array.isArray(configuredColumns)) {
                        return [];
                    }

                    return configuredColumns as SwMeteorEntityDataTableColumn[];
                });

                function buildCriteria() {
                    return setupProps.criteria ?? new Criteria(page.value, limit.value);
                }

                function loadData(): Promise<void> {
                    return Promise.resolve();
                }

                function setSort(property: string, direction: SwMeteorEntityDataTableSortDirection): void {
                    sortBy.value = property;
                    sortDirection.value = direction;

                    setupContext.emit('sort-change', {
                        property,
                        dataIndex: property,
                        direction,
                        naturalSorting: false,
                    });
                }

                function setPage(nextPage: number): void {
                    page.value = nextPage;

                    setupContext.emit('page-change', {
                        page: page.value,
                        limit: limit.value,
                    });
                }

                function setLimit(nextLimit: number): void {
                    limit.value = nextLimit;

                    setupContext.emit('page-change', {
                        page: page.value,
                        limit: limit.value,
                    });
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
                    const nextSelectedIds = new Set(selectedIds.value);

                    if (payload.value) {
                        nextSelectedIds.add(payload.id);
                    } else {
                        nextSelectedIds.delete(payload.id);
                    }

                    setSelectedIds([
                        ...nextSelectedIds,
                    ]);
                }

                function onMultipleSelectionChange(payload: MultipleSelectionChangePayload): void {
                    const nextSelectedIds = new Set(selectedIds.value);

                    payload.selections.forEach((id) => {
                        if (payload.value) {
                            nextSelectedIds.add(id);
                        } else {
                            nextSelectedIds.delete(id);
                        }
                    });

                    setSelectedIds([
                        ...nextSelectedIds,
                    ]);
                }

                function onSearchValueChange(term: string): void {
                    setupContext.emit('search-change', term);
                }

                function onOpenDetails(record: SwMeteorEntityDataTableRecord): void {
                    setupContext.emit('open-details', {
                        id: record.id,
                    });
                }

                function onContextSelect(payload: SwMeteorEntityDataTableContextSelectPayload): void {
                    setupContext.emit('context-select', payload);
                }

                function onReload(): void {
                    void loadData();
                }

                const disableSettingsTable = computed(() => !setupProps.showSettings);

                const blockDataScope = computed(() => ({
                    dataSource: dataSource.value,
                    totalItems: totalItems.value,
                    page: page.value,
                    limit: limit.value,
                    sortBy: sortBy.value,
                    sortDirection: sortDirection.value,
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
                        dataSource.value = nextRecords ?? [];
                        totalItems.value = resolveTotal(nextRecords, setupProps.total);
                    },
                );

                watch(
                    () => setupProps.total,
                    (nextTotal) => {
                        totalItems.value = resolveTotal(dataSource.value, nextTotal);
                    },
                );

                return {
                    public: {
                        dataSource,
                        totalItems,
                        page,
                        limit,
                        sortBy,
                        sortDirection,
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
                        disableSettingsTable,
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
</script>

<style lang="scss">
.sw-meteor-entity-data-table {
    width: 100%;
}
</style>
