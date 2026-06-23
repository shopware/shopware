/**
 * @sw-package framework
 */

/* eslint-disable sw-deprecation-rules/private-feature-declarations */

import { defineComponent, getCurrentInstance, onMounted, ref, watch, type PropType } from 'vue';
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
import './sw-meteor-entity-data-table.scss';

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

        const {
            records,
            total,
            loading,
            state,
            selectedIds,
            selection,
            resolvedColumns,
            load,
            reload,
            buildCriteria,
            setPage,
            setLimit,
            setSearchTerm,
            setSort,
            setSelectedIds,
            openDetail,
            columnsWithSlots,
            hasColumnSlot,
            hasPreviewSlot,
            normalizeSlotScope,
            getColumnValue,
            pruneSelection,
            itemToDelete,
            isDeleting,
            bulkDeleteIds,
            isBulkDeleting,
            openDeleteModal,
            closeDeleteModal,
            confirmDelete,
            openBulkDeleteModal,
            closeBulkDeleteModal,
            confirmBulkDelete,
            isColumnInlineEditable,
            isInlineEditing,
            startInlineEdit,
            getInlineEditValue,
            updateInlineEditValue,
            saveInlineEdit,
            cancelInlineEdit,
        } = createExtendableSetup(
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
                    },
                };
            },
        );

        onMounted(() => {
            translator.value = (key: string) => {
                return instance?.proxy?.$t?.(key) ?? instance?.appContext.config.globalProperties.$t?.(key) ?? key;
            };

            void load.value();
        });

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
            void setPage.value(page);
            emit('page-change', {
                page,
                limit: state.value.limit,
            });
        };

        const handlePaginationLimitChange = (limit: number) => {
            state.value.page = 1;
            void setLimit.value(limit);
            emit('page-change', {
                page: 1,
                limit,
            });
        };

        const handleSortChange = (sortBy: string, sortDirection: 'ASC' | 'DESC') => {
            state.value.page = 1;
            void setSort.value(sortBy, sortDirection, getColumnForSort(sortBy)?.naturalSorting ?? false);
            emit('column-sort', getColumnForSort(sortBy), sortDirection);
        };

        const handleSearchValueChange = (searchTerm: string) => {
            state.value.page = 1;
            void setSearchTerm.value(searchTerm);
            emit('search-value-change', searchTerm);
        };

        const handleSelectionChange = ({ id, value }: { id: string; value: boolean }) => {
            const item = records.value.find((record) => record.id === id);
            const ids = value
                ? [
                      ...selectedIds.value,
                      id,
                  ]
                : selectedIds.value.filter((selectedId) => selectedId !== id);

            setSelectedIds.value(ids);
            emit('selected-ids-change', selectedIds.value);
            emit('selection-change', selection.value, selectedIds.value.length);
            emit('select-item', selection.value, item, value);
        };

        const handleMultipleSelectionChange = ({ selections, value }: { selections: string[]; value: boolean }) => {
            const ids = value
                ? Array.from(
                      new Set([
                          ...selectedIds.value,
                          ...selections,
                      ]),
                  )
                : selectedIds.value.filter((selectedId) => !selections.includes(selectedId));

            setSelectedIds.value(ids);
            emit('selected-ids-change', selectedIds.value);
            emit('selection-change', selection.value, selectedIds.value.length);
            emit('select-all-items', selection.value);
        };

        const handleContextSelect = (payload: { key: string; data: MeteorEntityTableRecord }) => {
            emit('context-select', payload);
        };

        watch(records, () => {
            const previousSelectedIds = [...selectedIds.value];

            pruneSelection.value();

            if (previousSelectedIds.length !== selectedIds.value.length) {
                emit('selected-ids-change', selectedIds.value);
                emit('selection-change', selection.value, selectedIds.value.length);
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
                if (state.value.page !== page) {
                    state.value.page = page;
                }

                if (state.value.limit !== limit) {
                    state.value.limit = limit;
                }

                if (state.value.searchTerm !== searchTerm) {
                    state.value.searchTerm = searchTerm;
                }

                if (state.value.sortBy !== sortBy) {
                    state.value.sortBy = sortBy;
                }

                if (state.value.sortDirection !== sortDirection) {
                    state.value.sortDirection = sortDirection;
                }

                if (state.value.naturalSorting !== naturalSorting) {
                    state.value.naturalSorting = naturalSorting;
                }
            },
        );

        return {
            records,
            total,
            loading,
            state,
            selectedIds,
            selection,
            resolvedColumns,
            load,
            reload,
            buildCriteria,
            setPage,
            setLimit,
            setSearchTerm,
            setSort,
            setSelectedIds,
            openDetail,
            columnsWithSlots,
            hasColumnSlot,
            hasPreviewSlot,
            normalizeSlotScope,
            getColumnValue,
            itemToDelete,
            isDeleting,
            bulkDeleteIds,
            isBulkDeleting,
            openDeleteModal,
            closeDeleteModal,
            confirmDelete,
            openBulkDeleteModal,
            closeBulkDeleteModal,
            confirmBulkDelete,
            isColumnInlineEditable,
            isInlineEditing,
            startInlineEdit,
            getInlineEditValue,
            updateInlineEditValue,
            saveInlineEdit,
            cancelInlineEdit,
            handlePaginationCurrentPageChange,
            handlePaginationLimitChange,
            handleSortChange,
            handleSearchValueChange,
            handleSelectionChange,
            handleMultipleSelectionChange,
            handleContextSelect,
        };
    },
});
