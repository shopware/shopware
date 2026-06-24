/**
 * @sw-package framework
 */

/* eslint-disable sw-deprecation-rules/private-feature-declarations */

import type { ComputedRef, Reactive, Ref } from 'vue';
import type Criteria from 'src/core/data/criteria.data';

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

export type MeteorEntityTableColumnDefinition = {
    property: string;
    label: string;
    dataIndex?: string;
    sortField?: string;
    sortFields?: string[];
    naturalSorting?: boolean;
    renderer?: 'text' | 'number' | 'price' | 'badge';
    primary?: boolean;
    routerLink?: string;
    inlineEdit?: string | boolean;
    allowResize?: boolean;
    cellWrap?: 'nowrap' | 'normal';
    clickable?: boolean;
    previewImage?: string;
    rendererOptions?: unknown;
    visible?: boolean;
    width?: number | string;
    sortable?: boolean;
    [key: string]: unknown;
};

export type MeteorEntityTableColumn = {
    property: string;
    label: string;
    position?: number;
    renderer: 'text' | 'number' | 'price' | 'badge';
    clickable?: boolean;
    allowResize?: boolean;
    cellWrap?: 'nowrap' | 'normal';
    previewImage?: string;
    rendererOptions?: unknown;
    visible?: boolean;
    sortable?: boolean;
    [key: string]: unknown;
};

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
