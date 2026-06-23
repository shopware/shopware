<template>
    <div class="sw-meteor-entity-data-table">
        <mt-data-table
            class="sw-meteor-entity-data-table__table"
            :data-source="records"
            :columns="resolvedColumns"
            :current-page="state.page"
            :pagination-limit="state.limit"
            :pagination-options="paginationOptions"
            :pagination-total-items="total"
            :sort-by="state.sort?.property ?? ''"
            :sort-direction="state.sort?.direction ?? 'ASC'"
            :search-value="state.searchTerm"
            :is-loading="loading"
            :layout="layout"
            :allow-row-selection="selectable"
            :allow-bulk-delete="selectable && allowDelete"
            :selected-rows="selectedIds"
            :disable-search="!searchable"
            :enable-reload="reloadable"
            :disable-edit="!allowEdit"
            :disable-delete="!allowDelete"
            :disable-settings-table="hideTableSettings"
            :column-changes="tableColumnChanges"
            :show-outlines="showOutlines"
            :show-stripes="showStripes"
            :enable-outline-framing="enableOutlineFraming"
            :enable-row-numbering="enableRowNumbering"
            :additional-context-buttons="additionalContextButtons"
            @sort-change="setSort"
            @pagination-current-page-change="setPage"
            @pagination-limit-change="setLimit"
            @search-value-change="setSearchTerm"
            @selection-change="onSelectionChange"
            @multiple-selection-change="onMultipleSelectionChange"
            @reload="reload"
            @open-details="openDetail"
            @bulk-delete="openBulkDeleteModal"
            @item-delete="openDeleteModal"
            @context-select="onContextSelect"
            @change-show-outlines="setShowOutlines"
            @change-show-stripes="setShowStripes"
            @change-outline-framing="setEnableOutlineFraming"
            @change-enable-row-numbering="setEnableRowNumbering"
        >
            <template
                v-for="column in inlineEditableColumns"
                :key="column.property"
                #[`column-${column.property}`]="scope"
            >
                <sw-meteor-entity-data-table-cell
                    :column="column"
                    :value="getRecordValue(getSlotRecord(scope), column.property)"
                    :text-value="renderRecordValue(getSlotRecord(scope), column.property)"
                    :number-value="renderNumberRecordValue(getSlotRecord(scope), column.property)"
                    :preview-image-value="renderRecordValue(getSlotRecord(scope), column.previewImage ?? '')"
                    :is-inline-editing="isInlineEditing(getSlotRecord(scope))"
                    :is-last-inline-editable-column="isLastInlineEditableColumn(column)"
                    :saving-inline-edit="savingInlineEdit"
                    :has-legacy-preview-slot="hasLegacyPreviewSlot(column)"
                    :has-column-slot="Boolean($slots[`column-${column.property}`])"
                    @start-inline-edit="startInlineEdit(getSlotRecord(scope))"
                    @update-record-value="updateRecordValue(getSlotRecord(scope), column.property, $event)"
                    @cancel-inline-edit="cancelInlineEdit"
                    @save-inline-edit="saveInlineEdit(getSlotRecord(scope))"
                    @open-detail="openDetailFromSlotScope(scope)"
                >
                    <template #legacy-preview>
                        <slot
                            :name="getLegacyPreviewSlotName(column)"
                            v-bind="normalizeLegacyPreviewSlotScope(scope, column)"
                        />
                    </template>

                    <template #column>
                        <slot
                            :name="`column-${column.property}`"
                            v-bind="normalizeInlineEditSlotScope(scope, column)"
                        />
                    </template>
                </sw-meteor-entity-data-table-cell>
            </template>

            <template
                v-for="name in forwardedSlotNames"
                #[name]="scope"
            >
                <slot
                    :name="name"
                    v-bind="normalizeForwardedSlotScope(scope)"
                />
            </template>
        </mt-data-table>

        <sw-meteor-entity-data-table-delete-modal
            v-if="itemToDelete"
            :item="itemToDelete"
            :is-loading="deleting"
            @close="closeDeleteModal"
            @delete="deleteRecord"
        >
            <template #confirm-text="{ item }">
                <slot
                    name="delete-confirm-text"
                    :item="item"
                >
                    {{ $t('global.entity-components.deleteMessage') }}
                </slot>
            </template>

            <template #modal-footer="{ item, deleteItem, isLoading }">
                <slot
                    name="delete-modal-footer"
                    :item="item"
                    :delete-item="deleteItem"
                    :is-loading="isLoading"
                >
                    <mt-button
                        class="sw-meteor-entity-data-table__delete-cancel"
                        size="small"
                        variant="secondary"
                        @click="closeDeleteModal"
                    >
                        {{ $t('global.default.cancel') }}
                    </mt-button>

                    <mt-button
                        class="sw-meteor-entity-data-table__delete-confirm"
                        variant="critical"
                        size="small"
                        :is-loading="deleting"
                        @click="deleteRecord"
                    >
                        {{ $t('global.default.delete') }}
                    </mt-button>
                </slot>
            </template>
        </sw-meteor-entity-data-table-delete-modal>

        <sw-meteor-entity-data-table-bulk-delete-modal
            v-if="showBulkDeleteModal"
            :selection-count="selectedIds.length"
            :is-loading="bulkDeleting"
            @close="closeBulkDeleteModal"
            @delete="deleteSelectedRecords"
        >
            <template #confirm-text="{ selectionCount }">
                <slot
                    name="bulk-delete-confirm-text"
                    :selection-count="selectionCount"
                >
                    {{
                        $t(
                            'global.entity-components.deleteMessage',
                            { count: selectionCount },
                            selectionCount,
                        )
                    }}
                </slot>
            </template>

            <template #modal-footer="{ deleteItems, isLoading, selectionCount }">
                <slot
                    name="bulk-delete-modal-footer"
                    :delete-items="deleteItems"
                    :is-loading="isLoading"
                    :selection-count="selectionCount"
                >
                    <mt-button
                        class="sw-meteor-entity-data-table__bulk-delete-cancel"
                        size="small"
                        variant="secondary"
                        @click="closeBulkDeleteModal"
                    >
                        {{ $t('global.default.cancel') }}
                    </mt-button>

                    <mt-button
                        class="sw-meteor-entity-data-table__bulk-delete-confirm"
                        variant="critical"
                        size="small"
                        :is-loading="bulkDeleting"
                        @click="deleteSelectedRecords"
                    >
                        {{ $t('global.default.delete') }}
                    </mt-button>
                </slot>
            </template>
        </sw-meteor-entity-data-table-bulk-delete-modal>
    </div>
</template>

<script lang="ts">
/**
 * @sw-package framework
 */

import { defineComponent, getCurrentInstance, onMounted, watch } from 'vue';
import type { PropType, SetupContext } from 'vue';
import { createExtendableSetup } from 'src/app/adapter/composition-extension-system';
import type {
    SwMeteorEntityDataTableColumn,
    SwMeteorEntityDataTableContextButton,
    SwMeteorEntityDataTableLayout,
} from './sw-meteor-entity-data-table.types';
import type {
    ContextSelectPayload,
    SetupProps,
    SwMeteorEntityDataTablePrivateApi,
    SwMeteorEntityDataTableProps,
    SwMeteorEntityDataTablePublicApi,
    SwMeteorEntityDataTableRecord,
    SwMeteorEntityDataTableRouter,
} from './sw-meteor-entity-data-table.internal-types';
import { useMeteorTableColumns } from './composables/use-meteor-table-columns';
import { useMeteorTableCriteria } from './composables/use-meteor-table-criteria';
import { useMeteorTableDeleteActions } from './composables/use-meteor-table-delete-actions';
import { useMeteorTableInlineEdit } from './composables/use-meteor-table-inline-edit';
import { useMeteorTableSelection } from './composables/use-meteor-table-selection';
import { useMeteorTableSlots } from './composables/use-meteor-table-slots';
import { useMeteorTableState } from './composables/use-meteor-table-state';
import { useMeteorTableUserSettings } from './composables/use-meteor-table-user-settings';
// eslint-disable-next-line import/extensions
import SwMeteorEntityDataTableBulkDeleteModal from './components/sw-meteor-entity-data-table-bulk-delete-modal.vue';
// eslint-disable-next-line import/extensions
import SwMeteorEntityDataTableCell from './components/sw-meteor-entity-data-table-cell.vue';
// eslint-disable-next-line import/extensions
import SwMeteorEntityDataTableDeleteModal from './components/sw-meteor-entity-data-table-delete-modal.vue';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default defineComponent({
    name: 'SwMeteorEntityDataTable',

    components: {
        SwMeteorEntityDataTableBulkDeleteModal,
        SwMeteorEntityDataTableCell,
        SwMeteorEntityDataTableDeleteModal,
    },

    props: {
        repository: {
            type: Object as PropType<SwMeteorEntityDataTableProps['repository']>,
            required: true,
        },

        columns: {
            type: Array as PropType<SwMeteorEntityDataTableColumn[]>,
            required: true,
        },

        identifier: {
            type: String,
            required: false,
            default: '',
        },

        criteria: {
            type: Object as PropType<SwMeteorEntityDataTableProps['criteria']>,
            required: false,
            default: null,
        },

        criteriaResolver: {
            type: Function as unknown as PropType<SwMeteorEntityDataTableProps['criteriaResolver']>,
            required: false,
            default: null,
        },

        context: {
            type: Object as PropType<SwMeteorEntityDataTableProps['context']>,
            required: false,
            default: null,
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

        initialSearchTerm: {
            type: String,
            required: false,
            default: '',
        },

        initialSort: {
            type: Object as PropType<SwMeteorEntityDataTableProps['initialSort']>,
            required: false,
            default: null,
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

        layout: {
            type: String as PropType<SwMeteorEntityDataTableLayout>,
            required: false,
            default: 'default',
        },

        searchable: {
            type: Boolean,
            required: false,
            default: true,
        },

        reloadable: {
            type: Boolean,
            required: false,
            default: false,
        },

        selectable: {
            type: Boolean,
            required: false,
            default: false,
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

        allowInlineEdit: {
            type: Boolean,
            required: false,
            default: false,
        },

        allowDelete: {
            type: Boolean,
            required: false,
            default: false,
        },

        hideTableSettings: {
            type: Boolean,
            required: false,
            default: false,
        },

        additionalContextButtons: {
            type: Array as PropType<SwMeteorEntityDataTableContextButton[]>,
            required: false,
            default() {
                return [];
            },
        },
    },

    emits: [
        'state-change',
        'selection-change',
        'selected-ids-change',
        'load-success',
        'load-error',
        'open-detail',
        'delete-finish',
        'delete-failed',
        'bulk-delete-finish',
        'bulk-delete-failed',
        'context-select',
        'inline-edit-save',
        'inline-edit-cancel',
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
                let loadTable = (): Promise<void> => Promise.resolve();
                let resetInlineEdit = (): void => {};
                let syncSelectedRecordsWithLoadedRecords = (): void => {};
                const instanceRouter = getCurrentInstance()?.proxy?.$router as SwMeteorEntityDataTableRouter | undefined;

                const { resolvedColumns, inlineEditableColumns } = useMeteorTableColumns({
                    columns: () => setupProps.columns,
                });

                const {
                    state,
                    syncStateFromProps,
                    cloneState,
                    setPage,
                    setLimit,
                    setSearchTerm,
                    setSort,
                } = useMeteorTableState({
                    initialPage: () => setupProps.initialPage,
                    initialLimit: () => setupProps.initialLimit,
                    initialSearchTerm: () => setupProps.initialSearchTerm,
                    initialSort: () => setupProps.initialSort,
                    emitStateChange: (nextState) => {
                        setupContext.emit('state-change', nextState);
                    },
                    load: () => loadTable(),
                });

                const { tableColumnChanges, showOutlines, showStripes, enableOutlineFraming, enableRowNumbering, loadUserTableSettings, setShowOutlines, setShowStripes, setEnableOutlineFraming, setEnableRowNumbering } =
                    useMeteorTableUserSettings({
                        identifier: () => setupProps.identifier,
                        resolvedColumns,
                    });

                const { records, total, loading, buildCriteria, load, reload } = useMeteorTableCriteria({
                    repository: () => setupProps.repository,
                    criteria: () => setupProps.criteria,
                    criteriaResolver: () => setupProps.criteriaResolver,
                    context: () => setupProps.context,
                    state,
                    cloneState,
                    columns: () => setupProps.columns,
                    resetInlineEdit: () => resetInlineEdit(),
                    syncSelectedRecordsWithLoadedRecords: () => syncSelectedRecordsWithLoadedRecords(),
                    emitLoadSuccess: (payload) => {
                        setupContext.emit('load-success', payload);
                    },
                    emitLoadError: (payload) => {
                        setupContext.emit('load-error', payload);
                    },
                });
                loadTable = load;

                const {
                    selectedIds,
                    setSelectedIds,
                    onSelectionChange,
                    onMultipleSelectionChange,
                    syncSelectedRecordsWithLoadedRecords: syncSelectionWithLoadedRecords,
                } = useMeteorTableSelection({
                    records,
                    emitSelectionChange: (selection, selectionCount) => {
                        setupContext.emit('selection-change', selection, selectionCount);
                    },
                    emitSelectedIdsChange: (nextSelectedIds) => {
                        setupContext.emit('selected-ids-change', nextSelectedIds);
                    },
                });
                syncSelectedRecordsWithLoadedRecords = syncSelectionWithLoadedRecords;

                const {
                    currentInlineEditId,
                    savingInlineEdit,
                    getRecordValue,
                    updateRecordValue,
                    renderRecordValue,
                    renderNumberRecordValue,
                    isInlineEditing,
                    startInlineEdit,
                    saveInlineEdit,
                    cancelInlineEdit,
                    isLastInlineEditableColumn,
                    resetInlineEdit: resetCurrentInlineEdit,
                } = useMeteorTableInlineEdit({
                    repository: () => setupProps.repository,
                    context: () => setupProps.context,
                    allowInlineEdit: () => setupProps.allowInlineEdit,
                    inlineEditableColumns,
                    load,
                    emitInlineEditSave: (savePromise, record) => {
                        setupContext.emit('inline-edit-save', savePromise, record);
                    },
                    emitInlineEditCancel: (reloadPromise) => {
                        setupContext.emit('inline-edit-cancel', reloadPromise);
                    },
                });
                resetInlineEdit = resetCurrentInlineEdit;

                function openDetail(record: SwMeteorEntityDataTableRecord): void {
                    if (setupProps.detailRoute) {
                        void getRouter(instanceRouter)?.push({
                            name: setupProps.detailRoute,
                            params: {
                                id: record.id,
                            },
                        });
                    }

                    setupContext.emit('open-detail', {
                        id: record.id,
                        record,
                    });
                }

                function onContextSelect(payload: ContextSelectPayload): void {
                    setupContext.emit('context-select', {
                        key: payload.key,
                        id: payload.data.id,
                        record: payload.data,
                    });
                }

                const {
                    forwardedSlotNames,
                    getLegacyPreviewSlotName,
                    hasLegacyPreviewSlot,
                    getSlotRecord,
                    normalizeInlineEditSlotScope,
                    normalizeLegacyPreviewSlotScope,
                    openDetailFromSlotScope,
                    normalizeForwardedSlotScope,
                } = useMeteorTableSlots({
                    slots: setupContext.slots,
                    inlineEditableColumns,
                    isInlineEditing,
                    openDetail,
                });

                const {
                    itemToDelete,
                    deleting,
                    showBulkDeleteModal,
                    bulkDeleting,
                    openDeleteModal,
                    closeDeleteModal,
                    deleteRecord,
                    openBulkDeleteModal,
                    closeBulkDeleteModal,
                    deleteSelectedRecords,
                } = useMeteorTableDeleteActions({
                    repository: () => setupProps.repository,
                    context: () => setupProps.context,
                    selectable: () => setupProps.selectable,
                    allowDelete: () => setupProps.allowDelete,
                    selectedIds,
                    setSelectedIds,
                    load,
                    emitBulkDeleteFailed: (payload) => {
                        setupContext.emit('bulk-delete-failed', payload);
                    },
                    emitBulkDeleteFinish: (payload) => {
                        setupContext.emit('bulk-delete-finish', payload);
                    },
                    emitDeleteFailed: (payload) => {
                        setupContext.emit('delete-failed', payload);
                    },
                    emitDeleteFinish: (payload) => {
                        setupContext.emit('delete-finish', payload);
                    },
                });

                watch(
                    () => setupProps.criteria,
                    () => {
                        void load();
                    },
                );

                watch(
                    () => setupProps.context,
                    () => {
                        void load();
                    },
                );

                watch(
                    () =>
                        [
                            setupProps.initialPage,
                            setupProps.initialLimit,
                            setupProps.initialSearchTerm,
                            setupProps.initialSort?.property,
                            setupProps.initialSort?.direction,
                        ] as const,
                    () => {
                        if (syncStateFromProps()) {
                            void load();
                        }
                    },
                );

                onMounted(() => {
                    void loadUserTableSettings();
                    void load();
                });

                return {
                    public: {
                        records,
                        total,
                        loading,
                        state,
                        selectedIds,
                        resolvedColumns,
                        buildCriteria,
                        load,
                        reload,
                        setPage,
                        setLimit,
                        setSearchTerm,
                        setSort,
                        setSelectedIds,
                    },
                    private: {
                        onSelectionChange,
                        onMultipleSelectionChange,
                        openDetail,
                        openBulkDeleteModal,
                        closeBulkDeleteModal,
                        deleteSelectedRecords,
                        openDeleteModal,
                        closeDeleteModal,
                        deleteRecord,
                        onContextSelect,
                        inlineEditableColumns,
                        forwardedSlotNames,
                        currentInlineEditId,
                        savingInlineEdit,
                        getLegacyPreviewSlotName,
                        hasLegacyPreviewSlot,
                        getSlotRecord,
                        getRecordValue,
                        updateRecordValue,
                        renderRecordValue,
                        renderNumberRecordValue,
                        isInlineEditing,
                        startInlineEdit,
                        saveInlineEdit,
                        cancelInlineEdit,
                        isLastInlineEditableColumn,
                        normalizeInlineEditSlotScope,
                        normalizeLegacyPreviewSlotScope,
                        openDetailFromSlotScope,
                        itemToDelete,
                        deleting,
                        showBulkDeleteModal,
                        bulkDeleting,
                        tableColumnChanges,
                        showOutlines,
                        showStripes,
                        enableOutlineFraming,
                        enableRowNumbering,
                        setShowOutlines,
                        setShowStripes,
                        setEnableOutlineFraming,
                        setEnableRowNumbering,
                        normalizeForwardedSlotScope,
                    },
                };
            },
        );
    },
});

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
    height: 100%;
    min-height: 0;

    &__table.mt-data-table__layout-full {
        min-height: 0;
        margin: 0;
        // The internal table provides the top edge and is shifted by Meteor's half-pixel layout.
        border-top: 0;

        > .mt-card__content {
            flex: 1 1 auto;
            min-height: 0;
        }

        > .mt-card__footer {
            flex: 0 0 auto;
        }
    }

    &__inline-edit-cell,
    &__text-renderer-cell {
        display: flex;
        align-items: center;
        min-width: 0;
    }

    &__inline-edit-cell.is--inline-editing {
        gap: 8px;
    }

    &__inline-edit-field {
        flex: 1 1 auto;
        min-width: 0;
    }

    &__inline-edit-actions {
        display: flex;
        flex: 0 0 auto;
        gap: 4px;
    }

    &__preview-image-renderer {
        position: relative;
        flex: 0 0 auto;
        width: 34px;
        height: var(--scale-size-24);
        margin-right: 15px;
        border: 1px solid var(--color-border-secondary-default);
        border-radius: var(--border-radius-xs);
    }

    &__preview-image-renderer-item {
        position: absolute;
        top: 50%;
        left: 50%;
        max-width: calc(100% - 5px);
        max-height: calc(100% - 5px);
        transform: translate(-50%, -50%);
    }

    &__text-renderer,
    &__number-renderer {
        min-width: 0;
        margin: 0;
    }

    a.sw-meteor-entity-data-table__text-renderer {
        color: var(--color-text-primary-default);
        font-weight: var(--font-weight-medium);
        text-decoration: none;

        &:hover {
            color: var(--color-text-brand-default);
            text-decoration: underline;
        }
    }

    &__number-renderer {
        text-align: right;
        font-variant-numeric: tabular-nums;
    }
}
</style>
