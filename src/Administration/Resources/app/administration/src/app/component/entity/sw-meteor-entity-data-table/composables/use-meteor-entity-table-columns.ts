/**
 * @sw-package framework
 */

/* eslint-disable sw-deprecation-rules/private-feature-declarations */

import { computed } from 'vue';
import type { MeteorEntityTableColumn, MeteorEntityTableColumnDefinition } from '../sw-meteor-entity-data-table.types';

const COLUMN_POSITION_STEP = 100;
const DEFAULT_PREVIEW_IMAGE_FALLBACK = '/administration/administration/static/img/empty-states/media-empty-state.svg';

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
            const width = getColumnWidth(column.width);
            const clickable = column.primary === true || typeof column.routerLink === 'string' || column.clickable === true;
            const previewImageFallback = column.previewImage
                ? (column.previewImageFallback ?? DEFAULT_PREVIEW_IMAGE_FALLBACK)
                : column.previewImageFallback;

            return {
                property: column.property,
                label: translate(column.label),
                position: getColumnPosition(column, index),
                renderer: column.renderer ?? 'text',
                sortable: column.sortable !== false,
                ...(clickable ? { clickable: true } : {}),
                ...(typeof column.allowResize === 'boolean' ? { allowResize: column.allowResize } : {}),
                ...(column.cellWrap ? { cellWrap: column.cellWrap } : {}),
                ...(column.previewImage ? { previewImage: column.previewImage } : {}),
                ...(previewImageFallback ? { previewImageFallback } : {}),
                ...(column.rendererOptions ? { rendererOptions: column.rendererOptions } : {}),
                ...(typeof column.visible === 'boolean' ? { visible: column.visible } : {}),
                ...(typeof width === 'number' ? { width } : {}),
            };
        });
    });

    return {
        resolvedColumns,
    };
}
