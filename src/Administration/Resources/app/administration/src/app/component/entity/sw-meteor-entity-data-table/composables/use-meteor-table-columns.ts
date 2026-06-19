/**
 * @sw-package framework
 */

/* eslint-disable sw-deprecation-rules/private-feature-declarations */

import { computed } from 'vue';
import type { ComputedRef } from 'vue';
import type { SwMeteorEntityDataTableColumn } from '../sw-meteor-entity-data-table.types';
import type { SwMeteorEntityDataTableResolvedColumn } from '../sw-meteor-entity-data-table.internal-types';

type UseMeteorTableColumnsOptions = {
    columns: () => SwMeteorEntityDataTableColumn[];
};

export function useMeteorTableColumns(options: UseMeteorTableColumnsOptions): {
    resolvedColumns: ComputedRef<SwMeteorEntityDataTableResolvedColumn[]>;
    inlineEditableColumns: ComputedRef<SwMeteorEntityDataTableResolvedColumn[]>;
} {
    const resolvedColumns = computed<SwMeteorEntityDataTableResolvedColumn[]>(() => {
        // Column order follows declaration order; explicit positions are not part of the API.
        return options.columns().map((column, index) => resolveMeteorColumn(column, index * 100));
    });

    const inlineEditableColumns = computed<SwMeteorEntityDataTableResolvedColumn[]>(() => {
        return resolvedColumns.value.filter(isInlineEditableColumn);
    });

    return {
        resolvedColumns,
        inlineEditableColumns,
    };
}

export function isInlineEditableColumn(column: SwMeteorEntityDataTableResolvedColumn): boolean {
    return column.inlineEdit !== undefined;
}

export function resolveMeteorColumn(
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
