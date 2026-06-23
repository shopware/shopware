/**
 * @sw-package framework
 */

/* eslint-disable sw-deprecation-rules/private-feature-declarations */

import { computed } from 'vue';
import type { MeteorEntityTableColumn, MeteorEntityTableLegacyColumn } from '../sw-meteor-entity-data-table.types';

const COLUMN_POSITION_STEP = 100;

function getColumnPosition(column: MeteorEntityTableLegacyColumn, index: number): number {
    const columnWithPosition = column as MeteorEntityTableLegacyColumn & { position?: number };

    return typeof columnWithPosition.position === 'number'
        ? columnWithPosition.position
        : (index + 1) * COLUMN_POSITION_STEP;
}

function getColumnWidth(width: MeteorEntityTableLegacyColumn['width']): number | undefined {
    return typeof width === 'number' ? width : undefined;
}

export function useMeteorEntityTableColumns(
    columns: () => MeteorEntityTableLegacyColumn[],
    translate: (key: string) => string,
) {
    const resolvedColumns = computed<MeteorEntityTableColumn[]>(() => {
        return columns().map((column, index) => {
            const meteorColumn: MeteorEntityTableColumn = {
                property: column.property,
                label: translate(column.label),
                position: getColumnPosition(column, index),
                renderer: column.renderer ?? 'text',
            };

            if (column.primary === true || typeof column.routerLink === 'string') {
                meteorColumn.clickable = true;
            }

            if (typeof column.allowResize === 'boolean') {
                meteorColumn.allowResize = column.allowResize;
            }

            if (typeof column.visible === 'boolean') {
                meteorColumn.visible = column.visible;
            }

            const width = getColumnWidth(column.width);

            if (typeof width === 'number') {
                meteorColumn.width = width;
            }

            meteorColumn.sortable = column.sortable !== false;

            return meteorColumn;
        });
    });

    return {
        resolvedColumns,
    };
}
