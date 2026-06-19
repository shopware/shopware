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
                <div
                    class="sw-meteor-entity-data-table__inline-edit-cell"
                    :class="{ 'is--inline-editing': isInlineEditing(getSlotRecord(scope)) }"
                    @dblclick="startInlineEdit(getSlotRecord(scope))"
                >
                    <template v-if="isInlineEditing(getSlotRecord(scope))">
                        <sw-data-grid-inline-edit
                            class="sw-meteor-entity-data-table__inline-edit-field"
                            :value="getRecordValue(getSlotRecord(scope), column.property)"
                            :column="column"
                            compact
                            @update:value="updateRecordValue(getSlotRecord(scope), column.property, $event)"
                        />

                        <div
                            v-if="isLastInlineEditableColumn(column)"
                            class="sw-meteor-entity-data-table__inline-edit-actions"
                        >
                            <mt-button
                                class="sw-meteor-entity-data-table__inline-edit-cancel"
                                size="x-small"
                                square
                                variant="secondary"
                                :title="$t('global.default.cancel')"
                                :aria-label="$t('global.default.cancel')"
                                @click="cancelInlineEdit"
                            >
                                <mt-icon
                                    name="regular-times-xs"
                                    size="10px"
                                />
                            </mt-button>

                            <mt-button
                                class="sw-meteor-entity-data-table__inline-edit-save"
                                size="x-small"
                                square
                                variant="primary"
                                :is-loading="savingInlineEdit"
                                :title="$t('global.default.save')"
                                :aria-label="$t('global.default.save')"
                                @click="saveInlineEdit(getSlotRecord(scope))"
                            >
                                <mt-icon
                                    name="regular-checkmark-xxs"
                                    size="10px"
                                />
                            </mt-button>
                        </div>
                    </template>

                    <slot
                        v-else-if="$slots[`column-${column.property}`]"
                        :name="`column-${column.property}`"
                        v-bind="normalizeInlineEditSlotScope(scope, column)"
                    />

                    <template v-else>
                        <div
                            v-if="column.renderer === 'text'"
                            class="sw-meteor-entity-data-table__text-renderer-cell"
                        >
                            <div
                                v-if="column.previewImage"
                                class="sw-meteor-entity-data-table__preview-image-renderer"
                            >
                                <img
                                    class="sw-meteor-entity-data-table__preview-image-renderer-item"
                                    :src="renderRecordValue(getSlotRecord(scope), column.previewImage)"
                                    :alt="renderRecordValue(getSlotRecord(scope), column.property)"
                                />
                            </div>

                            <a
                                v-if="column.clickable"
                                class="sw-meteor-entity-data-table__text-renderer"
                                href="#"
                                @click.prevent="openDetailFromSlotScope(scope)"
                            >
                                {{ renderRecordValue(getSlotRecord(scope), column.property) }}
                            </a>

                            <p
                                v-else
                                class="sw-meteor-entity-data-table__text-renderer"
                            >
                                {{ renderRecordValue(getSlotRecord(scope), column.property) }}
                            </p>
                        </div>

                        <a
                            v-else-if="column.renderer === 'number' && column.clickable"
                            class="sw-meteor-entity-data-table__number-renderer"
                            href="#"
                            @click.prevent="openDetailFromSlotScope(scope)"
                        >
                            {{ renderNumberRecordValue(getSlotRecord(scope), column.property) }}
                        </a>

                        <p
                            v-else-if="column.renderer === 'number'"
                            class="sw-meteor-entity-data-table__number-renderer"
                        >
                            {{ renderNumberRecordValue(getSlotRecord(scope), column.property) }}
                        </p>

                        <span
                            v-else
                            class="sw-meteor-entity-data-table__text-renderer"
                        >
                            {{ renderRecordValue(getSlotRecord(scope), column.property) }}
                        </span>
                    </template>
                </div>
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

        <sw-modal
            v-if="itemToDelete"
            class="sw-meteor-entity-data-table__delete-modal"
            variant="small"
            :title="$t('global.default.warning')"
            @modal-close="closeDeleteModal"
        >
            <p class="sw-meteor-entity-data-table__confirm-delete-text">
                <slot
                    name="delete-confirm-text"
                    :item="itemToDelete"
                >
                    {{ $t('global.entity-components.deleteMessage') }}
                </slot>
            </p>

            <template #modal-footer>
                <slot
                    name="delete-modal-footer"
                    :item="itemToDelete"
                    :delete-item="deleteRecord"
                    :is-loading="deleting"
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
        </sw-modal>

        <sw-modal
            v-if="showBulkDeleteModal"
            class="sw-meteor-entity-data-table__bulk-delete-modal"
            variant="small"
            :title="$t('global.default.warning')"
            @modal-close="closeBulkDeleteModal"
        >
            <p class="sw-meteor-entity-data-table__confirm-bulk-delete-text">
                <slot
                    name="bulk-delete-confirm-text"
                    :selection-count="selectedIds.length"
                >
                    {{
                        $t(
                            'global.entity-components.deleteMessage',
                            { count: selectedIds.length },
                            selectedIds.length,
                        )
                    }}
                </slot>
            </p>

            <template #modal-footer>
                <slot
                    name="bulk-delete-modal-footer"
                    :delete-items="deleteSelectedRecords"
                    :is-loading="bulkDeleting"
                    :selection-count="selectedIds.length"
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
        </sw-modal>
    </div>
</template>

<script lang="ts">
/**
 * @sw-package framework
 */

import { computed, defineComponent, getCurrentInstance, onMounted, reactive, ref, watch } from 'vue';
import type { ComputedRef, PropType, Ref, SetupContext } from 'vue';
import type { Router } from 'vue-router';
import type EntityCollection from '@shopware-ag/meteor-admin-sdk/es/_internals/data/EntityCollection';
import type { ApiContext } from '@shopware-ag/meteor-admin-sdk/es/_internals/data/EntityCollection';
import type { Entity } from '@shopware-ag/meteor-admin-sdk/es/_internals/data/Entity';
import { createExtendableSetup } from 'src/app/adapter/composition-extension-system';
import type CriteriaType from 'src/core/data/criteria.data';
import type Repository from 'src/core/data/repository.data';
import { get as objectGet, set as objectSet } from 'src/core/service/utils/object.utils';
import type {
    SwMeteorEntityDataTableColumn,
    SwMeteorEntityDataTableColumnRenderer,
    SwMeteorEntityDataTableContextButton,
    SwMeteorEntityDataTableCriteriaResolver,
    SwMeteorEntityDataTableInlineEdit,
    SwMeteorEntityDataTableLayout,
    SwMeteorEntityDataTableSortDirection,
    SwMeteorEntityDataTableState,
} from './sw-meteor-entity-data-table.types';

type SwMeteorEntityDataTableEntityName = keyof EntitySchema.Entities;

type SwMeteorEntityDataTableRecord =
    | Entity<SwMeteorEntityDataTableEntityName>
    | {
          id: string;
          [key: string]: unknown;
      };

type SwMeteorEntityDataTableRecords = EntityCollection<SwMeteorEntityDataTableEntityName> | SwMeteorEntityDataTableRecord[];

type SwMeteorEntityDataTableResolvedColumn = {
    property: string;
    label: string;
    renderer: SwMeteorEntityDataTableColumnRenderer;
    position: number;
    sortable?: boolean;
    width?: number;
    visible?: boolean;
    clickable?: boolean;
    previewImage?: string;
    rendererOptions?: unknown;
    inlineEdit?: SwMeteorEntityDataTableInlineEdit;
};

type SwMeteorEntityDataTableColumnChange = {
    property?: string;
    position?: number;
    width?: number;
    visible?: boolean;
};

type SwMeteorEntityDataTableColumnChanges = Record<string, SwMeteorEntityDataTableColumnChange>;

type SwMeteorEntityDataTableProps = {
    repository: Repository<SwMeteorEntityDataTableEntityName>;
    columns: SwMeteorEntityDataTableColumn[];
    criteria?: CriteriaType | null;
    criteriaResolver?: SwMeteorEntityDataTableCriteriaResolver | null;
    context?: ApiContext | null;
    initialPage?: number;
    initialLimit?: number;
    initialSearchTerm?: string;
    initialSort?: SwMeteorEntityDataTableState['sort'] | null;
    paginationOptions?: number[];
    layout?: SwMeteorEntityDataTableLayout;
    searchable?: boolean;
    reloadable?: boolean;
    selectable?: boolean;
    detailRoute?: string | null;
    allowEdit?: boolean;
    allowInlineEdit?: boolean;
    allowDelete?: boolean;
    hideTableSettings?: boolean;
    additionalContextButtons?: SwMeteorEntityDataTableContextButton[];
};

type SelectionChangePayload = {
    id: string;
    value: boolean;
};

type MultipleSelectionChangePayload = {
    selections: string[];
    value: boolean;
};

type ContextSelectPayload = {
    key: string;
    data: SwMeteorEntityDataTableRecord;
};

type ForwardedSlotScope = Record<string, unknown> | undefined;

type SwMeteorEntityDataTableRouter = Pick<Router, 'push'>;

type SwMeteorEntityDataTablePublicApi = {
    records: Ref<SwMeteorEntityDataTableRecords>;
    total: Ref<number>;
    loading: Ref<boolean>;
    state: Ref<SwMeteorEntityDataTableState>;
    selectedIds: Ref<string[]>;
    resolvedColumns: ComputedRef<SwMeteorEntityDataTableResolvedColumn[]>;
    buildCriteria: () => CriteriaType;
    load: () => Promise<void>;
    reload: () => Promise<void>;
    setPage: (page: number) => Promise<void>;
    setLimit: (limit: number) => Promise<void>;
    setSearchTerm: (term: string) => Promise<void>;
    setSort: (property: string, direction: SwMeteorEntityDataTableSortDirection) => Promise<void>;
    setSelectedIds: (selectedIds: string[]) => void;
};

type SwMeteorEntityDataTablePrivateApi = {
    onSelectionChange: (payload: SelectionChangePayload) => void;
    onMultipleSelectionChange: (payload: MultipleSelectionChangePayload) => void;
    openDetail: (record: SwMeteorEntityDataTableRecord) => void;
    openBulkDeleteModal: () => void;
    closeBulkDeleteModal: () => void;
    deleteSelectedRecords: () => Promise<void>;
    openDeleteModal: (record: SwMeteorEntityDataTableRecord) => void;
    closeDeleteModal: () => void;
    deleteRecord: () => Promise<void>;
    onContextSelect: (payload: ContextSelectPayload) => void;
    inlineEditableColumns: ComputedRef<SwMeteorEntityDataTableResolvedColumn[]>;
    forwardedSlotNames: ComputedRef<string[]>;
    currentInlineEditId: Ref<string | null>;
    savingInlineEdit: Ref<boolean>;
    getSlotRecord: (scope: ForwardedSlotScope) => SwMeteorEntityDataTableRecord | null;
    getRecordValue: (record: SwMeteorEntityDataTableRecord | null, property: string) => unknown;
    updateRecordValue: (record: SwMeteorEntityDataTableRecord | null, property: string, value: unknown) => void;
    renderRecordValue: (record: SwMeteorEntityDataTableRecord | null, property: string) => string;
    renderNumberRecordValue: (record: SwMeteorEntityDataTableRecord | null, property: string) => string;
    isInlineEditing: (record: SwMeteorEntityDataTableRecord | null) => boolean;
    startInlineEdit: (record: SwMeteorEntityDataTableRecord | null) => void;
    saveInlineEdit: (record: SwMeteorEntityDataTableRecord | null) => Promise<void>;
    cancelInlineEdit: () => Promise<void>;
    isLastInlineEditableColumn: (column: SwMeteorEntityDataTableResolvedColumn) => boolean;
    normalizeInlineEditSlotScope: (
        scope: ForwardedSlotScope,
        column: SwMeteorEntityDataTableResolvedColumn,
    ) => Record<string, unknown>;
    openDetailFromSlotScope: (scope: ForwardedSlotScope) => void;
    itemToDelete: Ref<SwMeteorEntityDataTableRecord | null>;
    deleting: Ref<boolean>;
    showBulkDeleteModal: Ref<boolean>;
    bulkDeleting: Ref<boolean>;
    tableColumnChanges: SwMeteorEntityDataTableColumnChanges;
    showOutlines: Ref<boolean>;
    showStripes: Ref<boolean>;
    enableOutlineFraming: Ref<boolean>;
    enableRowNumbering: Ref<boolean>;
    setShowOutlines: (value: boolean) => void;
    setShowStripes: (value: boolean) => void;
    setEnableOutlineFraming: (value: boolean) => void;
    setEnableRowNumbering: (value: boolean) => void;
    normalizeForwardedSlotScope: (scope: ForwardedSlotScope) => Record<string, unknown>;
};

declare global {
    interface ComponentPublicApiMapping {
        'sw-meteor-entity-data-table': SwMeteorEntityDataTablePublicApi;
    }
}

type SetupProps = SwMeteorEntityDataTableProps & Record<string, unknown>;

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
            type: String as PropType<SwMeteorEntityDataTableProps['layout']>,
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
                const { Criteria } = Shopware.Data;
                const records: Ref<SwMeteorEntityDataTableRecords> = ref([]);
                const total = ref(0);
                const loading = ref(false);
                const itemToDelete: Ref<SwMeteorEntityDataTableRecord | null> = ref(null);
                const deleting = ref(false);
                const showBulkDeleteModal = ref(false);
                const bulkDeleting = ref(false);
                const selectedIds = ref<string[]>([]);
                const currentInlineEditId = ref<string | null>(null);
                const savingInlineEdit = ref(false);
                const tableColumnChanges: SwMeteorEntityDataTableColumnChanges = reactive({});
                const showOutlines = ref(true);
                const showStripes = ref(true);
                const enableOutlineFraming = ref(false);
                const enableRowNumbering = ref(false);
                const state = ref<SwMeteorEntityDataTableState>(buildStateFromProps());
                const instanceRouter = getCurrentInstance()?.proxy?.$router as SwMeteorEntityDataTableRouter | undefined;

                // Sequences overlapping loads so a slow earlier response cannot overwrite a newer one.
                let latestLoadToken = 0;

                const resolvedColumns = computed<SwMeteorEntityDataTableResolvedColumn[]>(() => {
                    // Column order follows declaration order; explicit positions are not part of the API.
                    return setupProps.columns.map((column, index) => resolveMeteorColumn(column, index * 100));
                });
                const inlineEditableColumns = computed<SwMeteorEntityDataTableResolvedColumn[]>(() => {
                    return resolvedColumns.value.filter(isInlineEditableColumn);
                });
                const inlineEditableColumnSlotNames = computed<Set<string>>(() => {
                    return new Set(inlineEditableColumns.value.map((column) => `column-${column.property}`));
                });
                const forwardedSlotNames = computed<string[]>(() => {
                    return Object.keys(setupContext.slots).filter((name) => !inlineEditableColumnSlotNames.value.has(name));
                });

                function buildStateFromProps(): SwMeteorEntityDataTableState {
                    return {
                        page: setupProps.initialPage ?? 1,
                        limit: setupProps.initialLimit ?? 25,
                        searchTerm: setupProps.initialSearchTerm ?? '',
                        ...(setupProps.initialSort
                            ? {
                                  sort: {
                                      ...setupProps.initialSort,
                                  },
                              }
                            : {}),
                    };
                }

                function areSortsEqual(
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

                function areStatesEqual(
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

                function syncStateFromProps(): void {
                    const nextState = buildStateFromProps();

                    if (areStatesEqual(state.value, nextState)) {
                        return;
                    }

                    state.value = nextState;
                }

                function cloneState(): SwMeteorEntityDataTableState {
                    const currentState = state.value;

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

                function emitStateChange(): void {
                    setupContext.emit('state-change', cloneState());
                }

                function buildCriteria(): CriteriaType {
                    const criteria = setupProps.criteria
                        ? Criteria.fromCriteria(setupProps.criteria)
                        : new Criteria(state.value.page, state.value.limit);

                    criteria.setPage(state.value.page);
                    criteria.setLimit(state.value.limit);
                    criteria.setTerm(state.value.searchTerm);
                    criteria.resetSorting();

                    const activeSort = state.value.sort;

                    if (!activeSort) {
                        return criteria;
                    }

                    const activeColumn = setupProps.columns.find((column) => column.property === activeSort.property);
                    const sortFields = toArray(activeColumn?.sortField ?? activeSort.property);

                    sortFields.forEach((field) => {
                        criteria.addSorting(
                            Criteria.sort(field, activeSort.direction, activeColumn?.naturalSorting === true),
                        );
                    });

                    return criteria;
                }

                async function load(): Promise<void> {
                    const loadToken = (latestLoadToken += 1);
                    loading.value = true;

                    try {
                        const searchContext = (setupProps.context ?? Shopware.Context.api) as typeof Shopware.Context.api;
                        let criteria: CriteriaType | null = buildCriteria();

                        if (setupProps.criteriaResolver) {
                            criteria = await setupProps.criteriaResolver({
                                criteria,
                                state: cloneState(),
                                context: searchContext as ApiContext,
                            });
                        }

                        if (loadToken !== latestLoadToken) {
                            return;
                        }

                        if (criteria === null) {
                            records.value = [];
                            total.value = 0;
                            currentInlineEditId.value = null;

                            setupContext.emit('load-success', {
                                records: records.value,
                                total: total.value,
                                state: cloneState(),
                            });

                            return;
                        }

                        const result = await setupProps.repository.search(criteria, searchContext);

                        if (loadToken !== latestLoadToken) {
                            return;
                        }

                        records.value = result;
                        total.value = resolveTotal(result);
                        currentInlineEditId.value = null;

                        setupContext.emit('load-success', {
                            records: result,
                            total: total.value,
                            state: cloneState(),
                        });
                    } catch (error) {
                        if (loadToken !== latestLoadToken) {
                            return;
                        }

                        setupContext.emit('load-error', {
                            error,
                            state: cloneState(),
                        });
                    } finally {
                        if (loadToken === latestLoadToken) {
                            loading.value = false;
                        }
                    }
                }

                function setPage(nextPage: number): Promise<void> {
                    state.value = {
                        ...state.value,
                        page: nextPage,
                    };

                    emitStateChange();

                    return load();
                }

                function setLimit(nextLimit: number): Promise<void> {
                    state.value = {
                        ...state.value,
                        page: 1,
                        limit: nextLimit,
                    };

                    emitStateChange();

                    return load();
                }

                function setSearchTerm(term: string): Promise<void> {
                    state.value = {
                        ...state.value,
                        page: 1,
                        searchTerm: term,
                    };

                    emitStateChange();

                    return load();
                }

                function setSort(
                    property: string,
                    direction: SwMeteorEntityDataTableSortDirection,
                ): Promise<void> {
                    state.value = {
                        ...state.value,
                        page: 1,
                        sort: {
                            property,
                            direction,
                        },
                    };

                    emitStateChange();

                    return load();
                }

                function setSelectedIds(nextSelectedIds: string[]): void {
                    const uniqueSelectedIds = nextSelectedIds.filter((id, index) => nextSelectedIds.indexOf(id) === index);

                    selectedIds.value = uniqueSelectedIds;

                    setupContext.emit('selection-change', [
                        ...uniqueSelectedIds,
                    ]);
                }

                function onSelectionChange(payload: SelectionChangePayload): void {
                    if (payload.value) {
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
                        setSelectedIds([
                            ...selectedIds.value,
                            ...payload.selections,
                        ]);
                        return;
                    }

                    setSelectedIds(selectedIds.value.filter((id) => !payload.selections.includes(id)));
                }

                function reload(): Promise<void> {
                    return load();
                }

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

                function getSlotRecord(scope: ForwardedSlotScope): SwMeteorEntityDataTableRecord | null {
                    const normalizedScope = normalizeForwardedSlotScope(scope);
                    const candidate = normalizedScope.item ?? normalizedScope.data;

                    if (!isTableRecord(candidate)) {
                        return null;
                    }

                    return candidate;
                }

                function getRecordValue(record: SwMeteorEntityDataTableRecord | null, property: string): unknown {
                    if (!record) {
                        return '';
                    }

                    return objectGet(record, property, '');
                }

                function updateRecordValue(
                    record: SwMeteorEntityDataTableRecord | null,
                    property: string,
                    value: unknown,
                ): void {
                    if (!record) {
                        return;
                    }

                    objectSet(record as Record<string, unknown>, property, value);
                }

                function renderRecordValue(record: SwMeteorEntityDataTableRecord | null, property: string): string {
                    const value = getRecordValue(record, property);

                    if (value === null || value === undefined) {
                        return '';
                    }

                    return String(value);
                }

                function renderNumberRecordValue(record: SwMeteorEntityDataTableRecord | null, property: string): string {
                    return String(Number(getRecordValue(record, property)));
                }

                function isInlineEditing(record: SwMeteorEntityDataTableRecord | null): boolean {
                    return currentInlineEditId.value !== null && currentInlineEditId.value === record?.id;
                }

                function startInlineEdit(record: SwMeteorEntityDataTableRecord | null): void {
                    if (!setupProps.allowInlineEdit || !record || inlineEditableColumns.value.length <= 0) {
                        return;
                    }

                    if (currentInlineEditId.value !== null && currentInlineEditId.value !== record.id) {
                        return;
                    }

                    currentInlineEditId.value = record.id;
                }

                function saveInlineEdit(record: SwMeteorEntityDataTableRecord | null): Promise<void> {
                    if (!record || !isInlineEditing(record)) {
                        return Promise.resolve();
                    }

                    const saveContext = (setupProps.context ?? Shopware.Context.api) as typeof Shopware.Context.api;
                    const savePromise = setupProps.repository
                        .save(record as Entity<SwMeteorEntityDataTableEntityName>, saveContext)
                        .then(() => load());

                    savingInlineEdit.value = true;
                    setupContext.emit('inline-edit-save', savePromise, record);

                    void savePromise
                        .then(() => {
                            currentInlineEditId.value = null;
                        })
                        .catch(() => {
                            // Keep inline edit active so the user can correct and retry the failed save.
                        })
                        .finally(() => {
                            savingInlineEdit.value = false;
                        });

                    return savePromise.catch(() => {
                        return undefined;
                    });
                }

                function cancelInlineEdit(): Promise<void> {
                    if (currentInlineEditId.value === null) {
                        return Promise.resolve();
                    }

                    const reloadPromise = load();

                    currentInlineEditId.value = null;
                    setupContext.emit('inline-edit-cancel', reloadPromise);

                    return reloadPromise;
                }

                function isLastInlineEditableColumn(column: SwMeteorEntityDataTableResolvedColumn): boolean {
                    return inlineEditableColumns.value[inlineEditableColumns.value.length - 1]?.property === column.property;
                }

                function normalizeInlineEditSlotScope(
                    scope: ForwardedSlotScope,
                    column: SwMeteorEntityDataTableResolvedColumn,
                ): Record<string, unknown> {
                    const normalizedScope = normalizeForwardedSlotScope(scope);

                    return {
                        ...normalizedScope,
                        isInlineEdit: isInlineEditing(getSlotRecord(scope)) && isInlineEditableColumn(column),
                    };
                }

                function openDetailFromSlotScope(scope: ForwardedSlotScope): void {
                    const record = getSlotRecord(scope);

                    if (!record) {
                        return;
                    }

                    openDetail(record);
                }

                function openDeleteModal(record: SwMeteorEntityDataTableRecord): void {
                    if (!setupProps.allowDelete) {
                        return;
                    }

                    itemToDelete.value = record;
                }

                function openBulkDeleteModal(): void {
                    if (!setupProps.selectable || !setupProps.allowDelete || selectedIds.value.length <= 0) {
                        return;
                    }

                    showBulkDeleteModal.value = true;
                }

                function closeBulkDeleteModal(): void {
                    if (bulkDeleting.value) {
                        return;
                    }

                    showBulkDeleteModal.value = false;
                }

                async function deleteSelectedRecords(): Promise<void> {
                    const ids = [
                        ...selectedIds.value,
                    ];

                    if (!setupProps.selectable || !setupProps.allowDelete || ids.length <= 0) {
                        return;
                    }

                    bulkDeleting.value = true;

                    try {
                        const deleteContext = (setupProps.context ?? Shopware.Context.api) as typeof Shopware.Context.api;

                        await setupProps.repository.syncDeleted(ids, deleteContext);
                    } catch (error) {
                        setupContext.emit('bulk-delete-failed', {
                            ids,
                            error,
                        });
                        bulkDeleting.value = false;

                        return;
                    }

                    bulkDeleting.value = false;
                    showBulkDeleteModal.value = false;
                    setSelectedIds([]);

                    setupContext.emit('bulk-delete-finish', {
                        ids,
                    });

                    await load();
                }

                function closeDeleteModal(): void {
                    if (deleting.value) {
                        return;
                    }

                    itemToDelete.value = null;
                }

                async function deleteRecord(): Promise<void> {
                    const record = itemToDelete.value;

                    if (!record) {
                        return;
                    }

                    deleting.value = true;

                    try {
                        const deleteContext = (setupProps.context ?? Shopware.Context.api) as typeof Shopware.Context.api;

                        await setupProps.repository.delete(record.id, deleteContext);
                    } catch (error) {
                        setupContext.emit('delete-failed', {
                            id: record.id,
                            record,
                            error,
                        });
                        deleting.value = false;

                        return;
                    }

                    deleting.value = false;
                    itemToDelete.value = null;

                    if (selectedIds.value.includes(record.id)) {
                        setSelectedIds(selectedIds.value.filter((id) => id !== record.id));
                    }

                    setupContext.emit('delete-finish', {
                        id: record.id,
                        record,
                    });

                    await load();
                }

                function onContextSelect(payload: ContextSelectPayload): void {
                    setupContext.emit('context-select', {
                        key: payload.key,
                        id: payload.data.id,
                        record: payload.data,
                    });
                }

                function setShowOutlines(value: boolean): void {
                    showOutlines.value = value;
                }

                function setShowStripes(value: boolean): void {
                    showStripes.value = value;
                }

                function setEnableOutlineFraming(value: boolean): void {
                    enableOutlineFraming.value = value;
                }

                function setEnableRowNumbering(value: boolean): void {
                    enableRowNumbering.value = value;
                }

                function normalizeForwardedSlotScope(scope: ForwardedSlotScope): Record<string, unknown> {
                    const normalizedScope = {
                        ...(scope ?? {}),
                    };

                    if (!('item' in normalizedScope) && 'data' in normalizedScope) {
                        normalizedScope.item = normalizedScope.data;
                    }

                    if (!('column' in normalizedScope) && 'columnDefinition' in normalizedScope) {
                        normalizedScope.column = normalizedScope.columnDefinition;
                    }

                    return normalizedScope;
                }

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
                        syncStateFromProps();
                    },
                );

                onMounted(() => {
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

function toArray<TValue>(value: TValue | TValue[]): TValue[] {
    return Array.isArray(value) ? value : [value];
}

function isInlineEditableColumn(column: SwMeteorEntityDataTableResolvedColumn): boolean {
    return column.inlineEdit !== undefined;
}

function isTableRecord(value: unknown): value is SwMeteorEntityDataTableRecord {
    return (
        typeof value === 'object' &&
        value !== null &&
        'id' in value &&
        typeof (value as { id: unknown }).id === 'string'
    );
}

function resolveMeteorColumn(
    column: SwMeteorEntityDataTableColumn,
    position: number,
): SwMeteorEntityDataTableResolvedColumn {
    // sortField/naturalSorting are wrapper-only and must not reach mt-data-table.
    const meteorColumn = { ...column } as Record<string, unknown>;
    delete meteorColumn.sortField;
    delete meteorColumn.naturalSorting;

    return {
        ...meteorColumn,
        renderer: column.renderer ?? 'text',
        position,
    } as SwMeteorEntityDataTableResolvedColumn;
}

function resolveTotal(records: SwMeteorEntityDataTableRecords): number {
    if ('total' in records && typeof records.total === 'number') {
        return records.total;
    }

    return records.length;
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
