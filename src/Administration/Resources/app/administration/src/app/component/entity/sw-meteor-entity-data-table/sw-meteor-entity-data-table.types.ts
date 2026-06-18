/**
 * @sw-package framework
 */

/* eslint-disable sw-deprecation-rules/private-feature-declarations */

export type SwMeteorEntityDataTableColumnRenderer = 'text' | 'number' | 'price' | 'badge';

export type SwMeteorEntityDataTableInlineEdit = 'string' | 'number' | 'boolean';

/**
 * Mirrors MtColorBadgeVariant from @shopware-ag/meteor-component-library so consumers get the
 * same badge variants the mt-data-table badge renderer accepts.
 */
export type SwMeteorEntityDataTableBadgeVariant = 'default' | 'warning' | 'critical' | 'positive' | 'info';

type SwMeteorEntityDataTableBaseColumn = {
    property: string;
    label: string;
    sortable?: boolean;
    width?: number;
    visible?: boolean;
    inlineEdit?: SwMeteorEntityDataTableInlineEdit;

    /**
     * DAL field or fields used for sorting. Defaults to `property`.
     */
    sortField?: string | string[];
    naturalSorting?: boolean;
};

export type SwMeteorEntityDataTableTextColumn = SwMeteorEntityDataTableBaseColumn & {
    renderer?: 'text';
    clickable?: boolean;
    previewImage?: string;
};

export type SwMeteorEntityDataTableNumberColumn = SwMeteorEntityDataTableBaseColumn & {
    renderer: 'number';
    clickable?: boolean;
};

export type SwMeteorEntityDataTableBadgeColumn = SwMeteorEntityDataTableBaseColumn & {
    renderer: 'badge';
    rendererOptions: {
        renderItemBadge: (
            data: unknown,
            columnDefinition: SwMeteorEntityDataTableBadgeColumn,
        ) => {
            label: string;
            variant: SwMeteorEntityDataTableBadgeVariant;
        };
    };
};

export type SwMeteorEntityDataTablePriceColumn = SwMeteorEntityDataTableBaseColumn & {
    renderer: 'price';
    rendererOptions: {
        currencyId: string;
        currencyISOCode: string;
        source: 'gross' | 'net';
    };
    clickable?: boolean;
};

/**
 * Discriminated on `renderer`. `badge` and `price` require their `rendererOptions`, matching the
 * underlying mt-data-table column definitions. Column order follows declaration order; the wrapper
 * assigns the table `position` internally.
 */
export type SwMeteorEntityDataTableColumn =
    | SwMeteorEntityDataTableTextColumn
    | SwMeteorEntityDataTableNumberColumn
    | SwMeteorEntityDataTableBadgeColumn
    | SwMeteorEntityDataTablePriceColumn;

export type SwMeteorEntityDataTableSortDirection = 'ASC' | 'DESC';

export type SwMeteorEntityDataTableLayout = 'default' | 'full';

export type SwMeteorEntityDataTableContextButton = {
    key: string;
    label: string;
    type?: 'default' | 'active' | 'critical';
};

export type SwMeteorEntityDataTableState = {
    page: number;
    limit: number;
    searchTerm: string;
    sort?: {
        property: string;
        direction: SwMeteorEntityDataTableSortDirection;
    };
};
