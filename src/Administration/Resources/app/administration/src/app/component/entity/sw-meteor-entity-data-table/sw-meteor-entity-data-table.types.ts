/**
 * @sw-package framework
 */

/* eslint-disable sw-deprecation-rules/private-feature-declarations */

import type { ComputedRef, Reactive, Ref } from 'vue';
import type Criteria from 'src/core/data/criteria.data';
import type {
    BaseColumnDefinition as MtDataTableBaseColumnDefinition,
    ColumnChanges as MtDataTableColumnChanges,
} from '@shopware-ag/meteor-component-library/dist/esm/MtDataTable';

export type MeteorEntityTableSortDirection = 'ASC' | 'DESC';

export type MeteorEntityTableRecord = {
    id: string;
    [key: string]: unknown;
};

export type MeteorEntityTableResult = Array<MeteorEntityTableRecord> & {
    total?: number;
    criteria?: Criteria;
    context?: unknown;
};

export type MeteorEntityTableRepository = {
    search: (criteria: Criteria, context?: unknown) => Promise<MeteorEntityTableResult>;
    delete?: (id: string, context?: unknown) => Promise<unknown>;
    syncDeleted?: (ids: string[], context?: unknown) => Promise<unknown>;
    save?: (record: MeteorEntityTableRecord, context?: unknown) => Promise<unknown>;
};

export type MeteorEntityTableColumnRenderer = 'text' | 'number' | 'price' | 'badge';

export type MeteorEntityTableColumnDefinition = Omit<
    Partial<MtDataTableBaseColumnDefinition>,
    'label' | 'position' | 'property'
> & {
    property: string;
    label: string;
    dataIndex?: string;
    sortField?: string;
    sortFields?: string[];
    naturalSorting?: boolean;
    renderer?: MeteorEntityTableColumnRenderer;
    primary?: boolean;
    routerLink?: string;
    clickable?: boolean;
    previewImage?: string;
    previewImageFallback?: string;
    rendererOptions?: unknown;
    [key: string]: unknown;
};

export type MeteorEntityTableColumn = MtDataTableBaseColumnDefinition & {
    renderer: MeteorEntityTableColumnRenderer;
    clickable?: boolean;
    previewImage?: string;
    previewImageFallback?: string;
    rendererOptions?: unknown;
    [key: string]: unknown;
};

export type MeteorEntityTableColumnChanges = Record<string, MtDataTableColumnChanges>;

export type MeteorEntityTableState = {
    page: number;
    limit: number;
    searchTerm: string;
    sortBy: string;
    sortDirection: MeteorEntityTableSortDirection;
    naturalSorting: boolean;
};

export type MeteorEntityTableSelection = Record<string, MeteorEntityTableRecord>;

export type MeteorEntityTableLoadSuccessPayload = {
    records: MeteorEntityTableRecord[];
    total: number;
    state: MeteorEntityTableState;
};

export type MeteorEntityTableCriteriaResolver = (criteria: Criteria) => Criteria | null | Promise<Criteria | null>;

export type MeteorEntityTableCriteriaTransformContext = {
    baseCriteria: Criteria | null;
    columns: MeteorEntityTableColumnDefinition[];
    searchTerm: string;
};

export type MeteorEntityTableCriteriaTransform = (
    criteria: Criteria,
    state: MeteorEntityTableState,
    context: MeteorEntityTableCriteriaTransformContext,
) => Criteria | null | Promise<Criteria | null>;

export type MeteorEntityTableEmptyStateContext = {
    records: MeteorEntityTableRecord[];
    total: number;
    loading: boolean;
    state: MeteorEntityTableState;
    searchTerm: string;
};

export type MeteorEntityTableRouteQueryKeys = {
    page: string;
    limit: string;
    term: string;
    sortBy: string;
    sortDirection: string;
    naturalSorting: string;
};

export type MeteorEntityTableRouteQueryValue = string | string[] | number | boolean | null | undefined;

export type MeteorEntityTableRouteQuery = Record<string, MeteorEntityTableRouteQueryValue>;

export type MeteorEntityTableRoute = {
    name?: string | symbol | null;
    params?: Record<string, unknown>;
    query?: MeteorEntityTableRouteQuery;
};

export type MeteorEntityTableRouter = {
    push?: (route: MeteorEntityTableRoute) => Promise<unknown> | void;
    replace?: (route: MeteorEntityTableRoute) => Promise<unknown> | void;
};

export type MeteorEntityTablePublicApi = {
    records: Ref<MeteorEntityTableRecord[]>;
    total: Ref<number>;
    loading: Ref<boolean>;
    state: Reactive<MeteorEntityTableState>;
    selectedIds: Ref<string[]>;
    selection: Ref<MeteorEntityTableSelection>;
    resolvedColumns: ComputedRef<MeteorEntityTableColumn[]>;
    load: () => Promise<MeteorEntityTableRecord[]>;
    reload: () => Promise<MeteorEntityTableRecord[]>;
    buildCriteria: () => Promise<Criteria | null>;
    setPage: (page: number) => Promise<MeteorEntityTableRecord[]>;
    setLimit: (limit: number) => Promise<MeteorEntityTableRecord[]>;
    setSearchTerm: (searchTerm: string) => Promise<MeteorEntityTableRecord[]>;
    setSort: (
        sortBy: string,
        sortDirection: MeteorEntityTableSortDirection,
        naturalSorting?: boolean,
    ) => Promise<MeteorEntityTableRecord[]>;
    setSelectedIds: (ids: string[]) => void;
    openDetail: (record: MeteorEntityTableRecord) => void;
};

declare global {
    interface ComponentPublicApiMapping {
        'sw-meteor-entity-data-table': MeteorEntityTablePublicApi;
    }
}

export {};
