/**
 * @sw-package framework
 */

/* eslint-disable sw-deprecation-rules/private-feature-declarations */

import { computed } from 'vue';
import type { Slots } from 'vue';
import type { MeteorEntityTableColumn, MeteorEntityTableRecord } from '../sw-meteor-entity-data-table.types';

type UseMeteorEntityTableSlotsOptions = {
    resolvePreviewImageFallback?: (fallback: string) => string;
};

type MeteorColumnSlotScope = {
    data?: MeteorEntityTableRecord;
    columnDefinition?: MeteorEntityTableColumn;
    [key: string]: unknown;
};

function getPropertyValue(record: MeteorEntityTableRecord | undefined, property: string): unknown {
    if (!record) {
        return '';
    }

    return property.split('.').reduce<unknown>((value, path) => {
        if (value && typeof value === 'object' && path in value) {
            return (value as Record<string, unknown>)[path];
        }

        return '';
    }, record);
}

function getStringPropertyValue(record: MeteorEntityTableRecord | undefined, property: string): string {
    const value = getPropertyValue(record, property);

    return typeof value === 'string' ? value : '';
}

export function useMeteorEntityTableSlots(
    columns: () => MeteorEntityTableColumn[],
    slots: Slots,
    options: UseMeteorEntityTableSlotsOptions = {},
) {
    const hasColumnSlot = (property: string) => {
        return typeof slots[`column-${property}`] === 'function';
    };

    const hasPreviewSlot = (property: string) => {
        return typeof slots[`preview-${property}`] === 'function';
    };

    const columnsWithSlots = computed(() => {
        return columns().filter((column) => {
            return hasColumnSlot(column.property) || hasPreviewSlot(column.property) || !!column.previewImageFallback;
        });
    });

    const getColumnValue = (scope: MeteorColumnSlotScope) => {
        if (!scope.columnDefinition) {
            return '';
        }

        return getPropertyValue(scope.data, scope.columnDefinition.property);
    };

    const getColumnPreviewImage = (scope: MeteorColumnSlotScope) => {
        if (!scope.columnDefinition?.previewImage) {
            return '';
        }

        const previewImage = getStringPropertyValue(scope.data, scope.columnDefinition.previewImage);

        if (previewImage) {
            return previewImage;
        }

        const previewImageFallback = scope.columnDefinition.previewImageFallback;

        return previewImageFallback
            ? (options.resolvePreviewImageFallback?.(previewImageFallback) ?? previewImageFallback)
            : '';
    };

    return {
        columnsWithSlots,
        hasColumnSlot,
        hasPreviewSlot,
        getColumnValue,
        getColumnPreviewImage,
    };
}
