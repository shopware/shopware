/**
 * @sw-package framework
 */

/* eslint-disable sw-deprecation-rules/private-feature-declarations */

import { computed } from 'vue';
import type { MeteorEntityTableColumn, MeteorEntityTableColumnDefinition } from '../sw-meteor-entity-data-table.types';

const COLUMN_POSITION_STEP = 100;

function getColumnPosition(column: MeteorEntityTableColumnDefinition, index: number): number {
    const columnWithPosition = column as MeteorEntityTableColumnDefinition & { position?: number };

    return typeof columnWithPosition.position === 'number'
        ? columnWithPosition.position
        : (index + 1) * COLUMN_POSITION_STEP;
}

function getColumnWidth(width: MeteorEntityTableColumnDefinition['width']): number | undefined {
    return typeof width === 'number' ? width : undefined;
}

export function useMeteorEntityTableColumns(
    columns: () => MeteorEntityTableColumnDefinition[],
    translate: (key: string) => string,
) {
    const resolvedColumns = computed<MeteorEntityTableColumn[]>(() => {
        return columns().map((column, index) => {
            const meteorColumn: MeteorEntityTableColumn = {
                property: column.property,
                label: translate(column.label),
                position: getColumnPosition(column, index),
                renderer: column.renderer ?? 'text',
                sortable: column.sortable !== false,
            };

            if (column.primary === true || typeof column.routerLink === 'string' || column.clickable === true) {
                meteorColumn.clickable = true;
            }

            if (typeof column.allowResize === 'boolean') {
                meteorColumn.allowResize = column.allowResize;
            }

            if (column.cellWrap) {
                meteorColumn.cellWrap = column.cellWrap;
            }

            if (column.previewImage) {
                meteorColumn.previewImage = column.previewImage;
            }

            if (column.rendererOptions) {
                meteorColumn.rendererOptions = column.rendererOptions;
            }

            if (typeof column.visible === 'boolean') {
                meteorColumn.visible = column.visible;
            }

            const width = getColumnWidth(column.width);

            if (typeof width === 'number') {
                meteorColumn.width = width;
            }

            return meteorColumn;
        });
    });

    return {
        resolvedColumns,
    };
}
