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
            :selected-rows="selectedIds"
            :disable-search="!searchable"
            :enable-reload="reloadable"
            :disable-edit="true"
            :disable-delete="true"
            :disable-settings-table="true"
            @sort-change="setSort"
            @pagination-current-page-change="setPage"
            @pagination-limit-change="setLimit"
            @search-value-change="setSearchTerm"
            @selection-change="onSelectionChange"
            @multiple-selection-change="onMultipleSelectionChange"
            @reload="reload"
            @open-details="openDetail"
        >
            <template
                v-if="$slots.toolbar"
                #toolbar
            >
                <slot name="toolbar" />
            </template>

            <template
                v-if="$slots['empty-state']"
                #empty-state
            >
                <slot name="empty-state" />
            </template>
        </mt-data-table>
    </div>
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
import type {
    SwMeteorEntityDataTableColumn,
    SwMeteorEntityDataTableColumnRenderer,
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
};

type SwMeteorEntityDataTableProps = {
    repository: Repository<SwMeteorEntityDataTableEntityName>;
    columns: SwMeteorEntityDataTableColumn[];
    criteria?: CriteriaType | null;
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
    },

    emits: [
        'state-change',
        'selection-change',
        'load-success',
        'load-error',
        'open-detail',
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
                const selectedIds = ref<string[]>([]);
                const state = ref<SwMeteorEntityDataTableState>({
                    page: setupProps.initialPage ?? 1,
                    limit: setupProps.initialLimit ?? 25,
                    searchTerm: setupProps.initialSearchTerm ?? '',
                    sort: setupProps.initialSort ?? undefined,
                });
                const instanceRouter = getCurrentInstance()?.proxy?.$router as SwMeteorEntityDataTableRouter | undefined;

                // Sequences overlapping loads so a slow earlier response cannot overwrite a newer one.
                let latestLoadToken = 0;

                const resolvedColumns = computed<SwMeteorEntityDataTableResolvedColumn[]>(() => {
                    // Column order follows declaration order; explicit positions are not part of the API.
                    return setupProps.columns.map((column, index) => resolveMeteorColumn(column, index * 100));
                });

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
                        const result = await setupProps.repository.search(buildCriteria(), searchContext);

                        if (loadToken !== latestLoadToken) {
                            return;
                        }

                        records.value = result;
                        total.value = resolveTotal(result);

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
                    },
                };
            },
        );
    },
});

function toArray<TValue>(value: TValue | TValue[]): TValue[] {
    return Array.isArray(value) ? value : [value];
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

        > .mt-card__content {
            flex: 1 1 auto;
            min-height: 0;
        }

        > .mt-card__footer {
            flex: 0 0 auto;
        }
    }
}
</style>
