/**
 * @sw-package framework
 */

/* eslint-disable sw-deprecation-rules/private-feature-declarations */

export type SwMeteorEntityDataTableColumnRenderer = 'text' | 'number' | 'price' | 'badge';

export type SwMeteorEntityDataTableColumn = {
    property: string;
    dataIndex?: string;
    label: string;
    renderer?: SwMeteorEntityDataTableColumnRenderer;
    rendererOptions?: unknown;
    position?: number;
    sortable?: boolean;
    allowResize?: boolean;
    visible?: boolean;
    width?: string | number;
    naturalSorting?: boolean;
    useCustomSort?: boolean;
    clickable?: boolean;
};

export type SwMeteorEntityDataTableNormalizedColumn = {
    label: string;
    property: string;
    renderer: SwMeteorEntityDataTableColumnRenderer;
    position: number;
    rendererOptions?: unknown;
    sortable?: boolean;
    allowResize?: boolean;
    visible?: boolean;
    width?: number;
    clickable?: boolean;
};

export type SwMeteorEntityDataTableColumnSortMetadata = {
    property: string;
    dataIndex: string;
    naturalSorting: boolean;
    useCustomSort: boolean;
    sourceColumn: SwMeteorEntityDataTableColumn;
};

export type SwMeteorEntityDataTableColumnNormalization = {
    columns: SwMeteorEntityDataTableNormalizedColumn[];
    sortMetadataByProperty: Record<string, SwMeteorEntityDataTableColumnSortMetadata>;
};

export type SwMeteorEntityDataTableSelectionChangePayload = string[];

export type SwMeteorEntityDataTableOpenDetailsPayload = {
    id: string;
};

export type SwMeteorEntityDataTableContextSelectPayload<TEntity = unknown> = {
    key: string;
    data: TEntity;
};

/**
 * mt-data-table exposes one detail action. The wrapper maps both allowEdit and allowView
 * to that action when showActions is enabled, so Meteor cannot distinguish the edit/view label yet.
 */
export type SwMeteorEntityDataTableActionAclProps = {
    allowEdit?: boolean;
    allowView?: boolean;
    allowDelete?: boolean;
    allowBulkDelete?: boolean;
    showActions?: boolean;
};
