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
        layout="full"
        :disable-edit="!allowEdit"
        :disable-delete="!allowDelete"
        :disable-search="disableSearch"
        :disable-settings-table="!showSettings"
        :additional-context-buttons="additionalContextButtons"
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
    >
        <template
            v-for="column in columnsWithSlots"
            :key="column.property"
            #[`column-${column.property}`]="scope"
        >
            <slot
                v-if="hasColumnSlot(column.property)"
                :name="`column-${column.property}`"
                v-bind="normalizeSlotScope(scope)"
            />

            <template v-else>
                <div class="sw-meteor-entity-data-table__cell">
                    <span
                        v-if="hasPreviewSlot(column.property)"
                        class="sw-meteor-entity-data-table__preview"
                    >
                        <slot
                            :name="`preview-${column.property}`"
                            v-bind="normalizeSlotScope(scope)"
                        />
                    </span>

                    <sw-meteor-entity-data-table-inline-edit-cell
                        v-if="scope.data?.id && isColumnInlineEditable(column.property)"
                        :model-value="getInlineEditValue(scope.data, column.property)"
                        :is-editing="isInlineEditing(scope.data)"
                        @update:model-value="updateInlineEditValue(scope.data, column.property, $event)"
                        @start="startInlineEdit(scope.data)"
                        @save="saveInlineEdit(scope.data, column.property)"
                        @cancel="cancelInlineEdit"
                    />

                    <span
                        v-else
                        class="sw-meteor-entity-data-table__column-value"
                    >
                        {{ getColumnValue(scope) }}
                    </span>
                </div>
            </template>
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
        <template
            v-if="$slots['delete-confirm-text']"
            #delete-confirm-text="{ item }"
        >
            <slot
                name="delete-confirm-text"
                :item="item"
            />
        </template>

        <template
            v-if="$slots['delete-modal-footer']"
            #delete-modal-footer="scope"
        >
            <slot
                name="delete-modal-footer"
                v-bind="scope"
            />
        </template>

        <template
            v-if="$slots['delete-modal-cancel']"
            #delete-modal-cancel="scope"
        >
            <slot
                name="delete-modal-cancel"
                v-bind="scope"
            />
        </template>

        <template
            v-if="$slots['delete-modal-delete-item']"
            #delete-modal-delete-item="scope"
        >
            <slot
                name="delete-modal-delete-item"
                v-bind="scope"
            />
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
        <template
            v-if="$slots['bulk-modal-delete-confirm-text']"
            #bulk-modal-delete-confirm-text="{ selectionCount }"
        >
            <slot
                name="bulk-modal-delete-confirm-text"
                :selection-count="selectionCount"
            />
        </template>

        <template
            v-if="$slots['bulk-modal-cancel']"
            #bulk-modal-cancel="scope"
        >
            <slot
                name="bulk-modal-cancel"
                v-bind="scope"
            />
        </template>

        <template
            v-if="$slots['bulk-modal-delete-items']"
            #bulk-modal-delete-items="scope"
        >
            <slot
                name="bulk-modal-delete-items"
                v-bind="scope"
            />
        </template>
    </sw-meteor-entity-data-table-bulk-delete-modal>
</template>

<script lang="ts">
/**
 * @sw-package framework
 */

/* eslint-disable filename-rules/match, sw-deprecation-rules/private-feature-declarations */

import {
    defineComponent,
    getCurrentInstance,
    onMounted,
    reactive,
    ref,
    watch,
} from 'vue';
import type { PropType } from 'vue';
import type Criteria from 'src/core/data/criteria.data';
import { createExtendableSetup } from 'src/app/adapter/composition-extension-system';
import SwMeteorEntityDataTableBulkDeleteModal from './components/sw-meteor-entity-data-table-bulk-delete-modal';
import SwMeteorEntityDataTableDeleteModal from './components/sw-meteor-entity-data-table-delete-modal';
import SwMeteorEntityDataTableInlineEditCell from './components/sw-meteor-entity-data-table-inline-edit-cell';
import { useMeteorEntityTableColumns } from './composables/use-meteor-entity-table-columns';
import { useMeteorEntityTableCriteria } from './composables/use-meteor-entity-table-criteria';
import { useMeteorEntityTableDelete } from './composables/use-meteor-entity-table-delete';
import { useMeteorEntityTableInlineEdit } from './composables/use-meteor-entity-table-inline-edit';
import { useMeteorEntityTableSelection } from './composables/use-meteor-entity-table-selection';
import { useMeteorEntityTableSlots } from './composables/use-meteor-entity-table-slots';
import { useMeteorEntityTableState } from './composables/use-meteor-entity-table-state';
import type {
    MeteorEntityTableCriteriaResolver,
    MeteorEntityTableLegacyColumn,
    MeteorEntityTableLoadSuccessPayload,
    MeteorEntityTableRecord,
    MeteorEntityTableRepository,
} from './sw-meteor-entity-data-table.types';
import './sw-meteor-entity-data-table.types';

type SwMeteorEntityDataTableColumnChanges = Record<
    string,
    {
        position?: number;
        width?: number;
        visible?: boolean;
    }
>;

type SwMeteorEntityDataTableProps = {
    repository: MeteorEntityTableRepository;
    columns: MeteorEntityTableLegacyColumn[];
    criteria?: Criteria | null;
    criteriaResolver?: MeteorEntityTableCriteriaResolver | null;
    context?: unknown;
    detailRoute?: string | null;
    initialPage: number;
    initialLimit: number;
    initialSearchTerm: string;
    initialSortBy: string;
    initialSortDirection: 'ASC' | 'DESC';
    initialNaturalSorting: boolean;
    allowEdit: boolean;
    allowInlineEdit: boolean;
    allowDelete: boolean;
    showSelections: boolean;
    showSettings: boolean;
    disableSearch: boolean;
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
    (e: 'page-change', payload: { page: number; limit: number }): void;
    (e: 'column-sort', column: MeteorEntityTableLegacyColumn | undefined, direction: 'ASC' | 'DESC'): void;
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
    (e: 'inline-edit-save', promise: Promise<unknown>, record: MeteorEntityTableRecord): void;
    (e: 'inline-edit-cancel', promise: Promise<MeteorEntityTableRecord[]>): void;
};

export default defineComponent({
    name: 'sw-meteor-entity-data-table',

    components: {
        SwMeteorEntityDataTableBulkDeleteModal,
        SwMeteorEntityDataTableDeleteModal,
        SwMeteorEntityDataTableInlineEditCell,
    },

    props: {
        repository: {
            type: Object as PropType<MeteorEntityTableRepository>,
            required: true,
        },
        columns: {
            type: Array as PropType<MeteorEntityTableLegacyColumn[]>,
            required: true,
        },
        criteria: {
            type: Object as PropType<Criteria | null>,
            default: null,
        },
        criteriaResolver: {
            type: Function as unknown as PropType<MeteorEntityTableCriteriaResolver | null>,
            default: null,
        },
        context: {
            type: null as unknown as PropType<unknown>,
            default: undefined,
        },
        detailRoute: {
            type: String,
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
        allowInlineEdit: {
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
        'page-change',
        'column-sort',
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
        'inline-edit-save',
        'inline-edit-cancel',
    ],

    setup(rawProps, { emit: rawEmit, slots }) {
        const props = rawProps as SwMeteorEntityDataTableProps;
        const emit = rawEmit as SwMeteorEntityDataTableEmit;
        const instance = getCurrentInstance();
        const translator = ref((key: string) => {
            return instance?.appContext.config.globalProperties.$t?.(key) ?? key;
        });

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

                let buildTableCriteria: () => Promise<Criteria | null> = () => Promise.resolve(null);

                const tableState = useMeteorEntityTableState({
                    repository: props.repository,
                    context: props.context,
                    emit: (event, payload) => {
                        if (event === 'load-success') {
                            emit('load-success', payload as MeteorEntityTableLoadSuccessPayload);
                            return;
                        }

                        emit('load-error', payload);
                    },
                    buildCriteria: () => buildTableCriteria(),
                    initialPage: props.initialPage,
                    initialLimit: props.initialLimit,
                    initialSearchTerm: props.initialSearchTerm,
                    initialSortBy: props.initialSortBy,
                    initialSortDirection: props.initialSortDirection,
                    initialNaturalSorting: props.initialNaturalSorting,
                });

                ({ buildCriteria: buildTableCriteria } = useMeteorEntityTableCriteria({
                    state: tableState.state,
                    columns: props.columns,
                    criteria: props.criteria,
                    criteriaResolver: props.criteriaResolver,
                }));

                const tableSelection = useMeteorEntityTableSelection(() => tableState.records.value);
                const tableDelete = useMeteorEntityTableDelete({
                    repository: props.repository,
                    context: props.context,
                    reload: tableState.reload,
                    getSelectedIds: () => tableSelection.selectedIds.value,
                    setSelectedIds: tableSelection.setSelectedIds,
                    emit,
                });
                const tableInlineEdit = useMeteorEntityTableInlineEdit({
                    repository: props.repository,
                    context: props.context,
                    reload: tableState.reload,
                    emit,
                });
                const isInlineEditableColumn = (property: string) => {
                    return (
                        props.allowInlineEdit &&
                        props.columns.some((column) => {
                            return column.property === property && !!column.inlineEdit;
                        })
                    );
                };
                const tableSlots = useMeteorEntityTableSlots(() => resolvedTableColumns.value, slots, {
                    hasInternalColumnSlot: isInlineEditableColumn,
                    isInlineEdit: tableInlineEdit.isInlineEditing,
                });
                const columnChanges = reactive<SwMeteorEntityDataTableColumnChanges>({});

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

                const handlePaginationCurrentPageChange = (page: number) => {
                    void tableState.setPage(page);
                    emit('page-change', {
                        page,
                        limit: tableState.state.limit,
                    });
                };

                const handlePaginationLimitChange = (limit: number) => {
                    tableState.state.page = 1;
                    void tableState.setLimit(limit);
                    emit('page-change', {
                        page: 1,
                        limit,
                    });
                };

                const handleSortChange = (sortBy: string, sortDirection: 'ASC' | 'DESC') => {
                    const column = getColumnForSort(sortBy);

                    tableState.state.page = 1;
                    void tableState.setSort(sortBy, sortDirection, column?.naturalSorting ?? false);
                    emit('column-sort', column, sortDirection);
                };

                const handleSearchValueChange = (searchTerm: string) => {
                    tableState.state.page = 1;
                    void tableState.setSearchTerm(searchTerm);
                    emit('search-value-change', searchTerm);
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

                onMounted(() => {
                    translator.value = (key: string) => {
                        return instance?.proxy?.$t?.(key) ?? instance?.appContext.config.globalProperties.$t?.(key) ?? key;
                    };

                    void tableState.load();
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
                        if (tableState.state.page !== page) {
                            tableState.state.page = page;
                        }

                        if (tableState.state.limit !== limit) {
                            tableState.state.limit = limit;
                        }

                        if (tableState.state.searchTerm !== searchTerm) {
                            tableState.state.searchTerm = searchTerm;
                        }

                        if (tableState.state.sortBy !== sortBy) {
                            tableState.state.sortBy = sortBy;
                        }

                        if (tableState.state.sortDirection !== sortDirection) {
                            tableState.state.sortDirection = sortDirection;
                        }

                        if (tableState.state.naturalSorting !== naturalSorting) {
                            tableState.state.naturalSorting = naturalSorting;
                        }
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
                        setPage: tableState.setPage,
                        setLimit: tableState.setLimit,
                        setSearchTerm: tableState.setSearchTerm,
                        setSort: tableState.setSort,
                        setSelectedIds: tableSelection.setSelectedIds,
                        openDetail: openRecordDetail,
                    },
                    private: {
                        rebuildSelection: tableSelection.rebuildSelection,
                        pruneSelection: tableSelection.pruneSelection,
                        columnsWithSlots: tableSlots.columnsWithSlots,
                        hasColumnSlot: tableSlots.hasColumnSlot,
                        hasPreviewSlot: tableSlots.hasPreviewSlot,
                        columnChanges,
                        normalizeSlotScope: tableSlots.normalizeSlotScope,
                        getColumnValue: tableSlots.getColumnValue,
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
                        isColumnInlineEditable: isInlineEditableColumn,
                        isInlineEditing: tableInlineEdit.isInlineEditing,
                        startInlineEdit: tableInlineEdit.startInlineEdit,
                        getInlineEditValue: tableInlineEdit.getInlineEditValue,
                        updateInlineEditValue: tableInlineEdit.updateInlineEditValue,
                        saveInlineEdit: tableInlineEdit.saveInlineEdit,
                        cancelInlineEdit: tableInlineEdit.cancelInlineEdit,
                        handlePaginationCurrentPageChange,
                        handlePaginationLimitChange,
                        handleSortChange,
                        handleSearchValueChange,
                        handleSelectionChange,
                        handleMultipleSelectionChange,
                        handleContextSelect,
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
    }

    .sw-meteor-entity-data-table__column-value {
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .mt-data-table__table-wrapper-table-row:hover,
    .mt-data-table__table-wrapper-table-row:focus-within {
        .sw-meteor-entity-data-table-inline-edit-cell__start {
            opacity: 1;
        }
    }
}

.sw-meteor-entity-data-table-inline-edit-cell {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    min-width: 0;
    max-width: 100%;

    .sw-meteor-entity-data-table-inline-edit-cell__value {
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .sw-meteor-entity-data-table-inline-edit-cell__start {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 24px;
        height: 24px;
        padding: 0;
        color: var(--color-icon-secondary-default, #52667a);
        cursor: pointer;
        background: transparent;
        border: 0;
        opacity: 0;
        transition: opacity 0.12s ease-in-out;

        &:focus-visible {
            opacity: 1;
            outline: 2px solid var(--color-border-brand-default, #189eff);
            outline-offset: 2px;
        }
    }

    .sw-meteor-entity-data-table-inline-edit-cell__input {
        min-width: 120px;
    }
}
</style>
