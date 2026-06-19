/**
 * @sw-package framework
 */

/* eslint-disable sw-deprecation-rules/private-feature-declarations */

import type { ComputedRef, Ref } from 'vue';
import type { Router } from 'vue-router';
import type EntityCollection from '@shopware-ag/meteor-admin-sdk/es/_internals/data/EntityCollection';
import type { ApiContext } from '@shopware-ag/meteor-admin-sdk/es/_internals/data/EntityCollection';
import type { Entity } from '@shopware-ag/meteor-admin-sdk/es/_internals/data/Entity';
import type CriteriaType from 'src/core/data/criteria.data';
import type Repository from 'src/core/data/repository.data';
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

export type SwMeteorEntityDataTableEntityName = keyof EntitySchema.Entities;

export type SwMeteorEntityDataTableRecord =
    | Entity<SwMeteorEntityDataTableEntityName>
    | {
          id: string;
          [key: string]: unknown;
      };

export type SwMeteorEntityDataTableRecords =
    | EntityCollection<SwMeteorEntityDataTableEntityName>
    | SwMeteorEntityDataTableRecord[];

export type SwMeteorEntityDataTableSelection = Record<string, SwMeteorEntityDataTableRecord>;

export type SwMeteorEntityDataTableResolvedColumn = {
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

export type SwMeteorEntityDataTableColumnChange = {
    property?: string;
    position?: number;
    width?: number;
    visible?: boolean;
};

export type SwMeteorEntityDataTableColumnChanges = Record<string, SwMeteorEntityDataTableColumnChange>;

export type SwMeteorEntityDataTableUserConfigEntity = Entity<'user_config'>;

export type SwMeteorEntityDataTableUserConfigRepository = Repository<'user_config'>;

export type SwMeteorEntityDataTableAclService = {
    can: (privilege: string) => boolean;
};

export type SwMeteorEntityDataTableUserSettingColumn = {
    property?: string;
    dataIndex?: string;
    position?: number;
    width?: number;
    visible?: boolean;
};

export type SwMeteorEntityDataTableUserSettings = {
    columns?: SwMeteorEntityDataTableUserSettingColumn[] | SwMeteorEntityDataTableColumnChanges;
    columnChanges?: SwMeteorEntityDataTableColumnChanges;
    showOutlines?: boolean;
    showStripes?: boolean;
    enableOutlineFraming?: boolean;
    enableRowNumbering?: boolean;
};

export type SwMeteorEntityDataTableNormalizedUserSettings = {
    columnChanges: SwMeteorEntityDataTableColumnChanges;
    showOutlines?: boolean;
    showStripes?: boolean;
    enableOutlineFraming?: boolean;
    enableRowNumbering?: boolean;
};

export type SwMeteorEntityDataTableProps = {
    repository: Repository<SwMeteorEntityDataTableEntityName>;
    columns: SwMeteorEntityDataTableColumn[];
    identifier?: string;
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

export type SelectionChangePayload = {
    id: string;
    value: boolean;
};

export type MultipleSelectionChangePayload = {
    selections: string[];
    value: boolean;
};

export type ContextSelectPayload = {
    key: string;
    data: SwMeteorEntityDataTableRecord;
};

export type ForwardedSlotScope = Record<string, unknown> | undefined;

export type SwMeteorEntityDataTableRouter = Pick<Router, 'push'>;

export type SwMeteorEntityDataTablePublicApi = {
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

export type SwMeteorEntityDataTablePrivateApi = {
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
    getLegacyPreviewSlotName: (column: SwMeteorEntityDataTableResolvedColumn) => string;
    hasLegacyPreviewSlot: (column: SwMeteorEntityDataTableResolvedColumn) => boolean;
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
    normalizeLegacyPreviewSlotScope: (
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

export type SetupProps = SwMeteorEntityDataTableProps & Record<string, unknown>;
