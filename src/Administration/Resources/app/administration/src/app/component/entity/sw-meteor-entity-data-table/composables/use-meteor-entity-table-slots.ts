/**
 * @sw-package framework
 */

/* eslint-disable sw-deprecation-rules/private-feature-declarations */

import { computed } from 'vue';
import type { Slots } from 'vue';
import type { MeteorEntityTableColumn, MeteorEntityTableRecord } from '../sw-meteor-entity-data-table.types';

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

export function useMeteorEntityTableSlots(
    columns: () => MeteorEntityTableColumn[],
    slots: Slots,
    options: {
        hasInternalColumnSlot?: (property: string) => boolean;
    } = {},
) {
    const hasColumnSlot = (property: string) => {
        return typeof slots[`column-${property}`] === 'function';
    };

    const hasPreviewSlot = (property: string) => {
        return typeof slots[`preview-${property}`] === 'function';
    };

    const columnsWithSlots = computed(() => {
        return columns().filter((column) => {
            return (
                hasColumnSlot(column.property) ||
                hasPreviewSlot(column.property) ||
                options.hasInternalColumnSlot?.(column.property) === true
            );
        });
    });

    const getColumnValue = (scope: MeteorColumnSlotScope) => {
        if (!scope.columnDefinition) {
            return '';
        }

        return getPropertyValue(scope.data, scope.columnDefinition.property);
    };

    return {
        columnsWithSlots,
        hasColumnSlot,
        hasPreviewSlot,
        getColumnValue,
    };
}
