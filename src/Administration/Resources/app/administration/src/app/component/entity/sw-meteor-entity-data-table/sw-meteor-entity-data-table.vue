<template>
    <mt-data-table
        class="sw-meteor-entity-data-table"
        :columns="resolvedColumns"
        :data-source="records"
        :current-page="state.page"
        :pagination-limit="state.limit"
        :pagination-total-items="total"
        :is-loading="loading"
        :sort-by="state.sortBy"
        :sort-direction="state.sortDirection"
        :search-value="state.searchTerm"
        :allow-row-selection="showSelections"
        :selected-rows="selectedIds"
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
        confirm-text="Delete item?"
        cancel-text="Cancel"
        delete-text="Delete"
        @close="closeDeleteModal"
        @confirm="confirmDelete"
    >
        <template #delete-confirm-text="{ item }">
            <slot
                name="delete-confirm-text"
                :item="item"
            />
        </template>
    </sw-meteor-entity-data-table-delete-modal>

    <sw-meteor-entity-data-table-bulk-delete-modal
        v-if="bulkDeleteIds.length > 0"
        :selection-count="bulkDeleteIds.length"
        :is-deleting="isBulkDeleting"
        :confirm-text="`${bulkDeleteIds.length} items selected`"
        cancel-text="Cancel"
        delete-text="Delete"
        @close="closeBulkDeleteModal"
        @confirm="confirmBulkDelete"
    >
        <template #bulk-modal-delete-confirm-text="{ selectionCount }">
            <slot
                name="bulk-modal-delete-confirm-text"
                :selection-count="selectionCount"
            >
                {{ selectionCount }} items selected
            </slot>
        </template>
    </sw-meteor-entity-data-table-bulk-delete-modal>
</template>

<script>
/**
 * @sw-package framework
 */

/* eslint-disable filename-rules/match, sw-deprecation-rules/private-feature-declarations */

import component from './sw-meteor-entity-data-table.setup';

export default component;
</script>
