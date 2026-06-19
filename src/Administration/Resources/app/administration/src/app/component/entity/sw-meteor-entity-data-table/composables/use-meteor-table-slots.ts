/**
 * @sw-package framework
 */

/* eslint-disable sw-deprecation-rules/private-feature-declarations */

import { computed } from 'vue';
import type { ComputedRef, SetupContext } from 'vue';
import type {
    ForwardedSlotScope,
    SwMeteorEntityDataTableRecord,
    SwMeteorEntityDataTableResolvedColumn,
} from '../sw-meteor-entity-data-table.internal-types';
import { isInlineEditableColumn } from './use-meteor-table-columns';
import { isTableRecord } from '../sw-meteor-entity-data-table.utils';

type UseMeteorTableSlotsOptions = {
    slots: SetupContext['slots'];
    inlineEditableColumns: ComputedRef<SwMeteorEntityDataTableResolvedColumn[]>;
    isInlineEditing: (record: SwMeteorEntityDataTableRecord | null) => boolean;
    openDetail: (record: SwMeteorEntityDataTableRecord) => void;
};

export function useMeteorTableSlots(options: UseMeteorTableSlotsOptions): {
    forwardedSlotNames: ComputedRef<string[]>;
    getLegacyPreviewSlotName: (column: SwMeteorEntityDataTableResolvedColumn) => string;
    hasLegacyPreviewSlot: (column: SwMeteorEntityDataTableResolvedColumn) => boolean;
    getSlotRecord: (scope: ForwardedSlotScope) => SwMeteorEntityDataTableRecord | null;
    normalizeInlineEditSlotScope: (
        scope: ForwardedSlotScope,
        column: SwMeteorEntityDataTableResolvedColumn,
    ) => Record<string, unknown>;
    normalizeLegacyPreviewSlotScope: (
        scope: ForwardedSlotScope,
        column: SwMeteorEntityDataTableResolvedColumn,
    ) => Record<string, unknown>;
    openDetailFromSlotScope: (scope: ForwardedSlotScope) => void;
    normalizeForwardedSlotScope: (scope: ForwardedSlotScope) => Record<string, unknown>;
} {
    const inlineEditableColumnSlotNames = computed<Set<string>>(() => {
        return new Set(options.inlineEditableColumns.value.map((column) => `column-${column.property}`));
    });

    const inlineEditableLegacyPreviewSlotNames = computed<Set<string>>(() => {
        return new Set(
            options.inlineEditableColumns.value
                .map((column) => getLegacyPreviewSlotName(column))
                .filter((slotName) => options.slots[slotName]),
        );
    });

    const forwardedSlotNames = computed<string[]>(() => {
        return Object.keys(options.slots).filter((name) => {
            return !inlineEditableColumnSlotNames.value.has(name) && !inlineEditableLegacyPreviewSlotNames.value.has(name);
        });
    });

    function getLegacyPreviewSlotName(column: SwMeteorEntityDataTableResolvedColumn): string {
        return `preview-${column.property}`;
    }

    function hasLegacyPreviewSlot(column: SwMeteorEntityDataTableResolvedColumn): boolean {
        return Boolean(options.slots[getLegacyPreviewSlotName(column)]);
    }

    function getSlotRecord(scope: ForwardedSlotScope): SwMeteorEntityDataTableRecord | null {
        const normalizedScope = normalizeForwardedSlotScope(scope);
        const candidate = normalizedScope.item ?? normalizedScope.data;

        if (!isTableRecord(candidate)) {
            return null;
        }

        return candidate;
    }

    function normalizeInlineEditSlotScope(
        scope: ForwardedSlotScope,
        column: SwMeteorEntityDataTableResolvedColumn,
    ): Record<string, unknown> {
        const normalizedScope = normalizeForwardedSlotScope(scope);

        return {
            ...normalizedScope,
            isInlineEdit: options.isInlineEditing(getSlotRecord(scope)) && isInlineEditableColumn(column),
        };
    }

    function normalizeLegacyPreviewSlotScope(
        scope: ForwardedSlotScope,
        column: SwMeteorEntityDataTableResolvedColumn,
    ): Record<string, unknown> {
        const normalizedScope = normalizeForwardedSlotScope(scope);

        return {
            ...normalizedScope,
            column: normalizedScope.column ?? column,
            columnDefinition: normalizedScope.columnDefinition ?? column,
            compact: normalizedScope.compact ?? false,
        };
    }

    function openDetailFromSlotScope(scope: ForwardedSlotScope): void {
        const record = getSlotRecord(scope);

        if (!record) {
            return;
        }

        options.openDetail(record);
    }

    function normalizeForwardedSlotScope(scope: ForwardedSlotScope): Record<string, unknown> {
        const normalizedScope = {
            ...(scope ?? {}),
        };

        if (!('item' in normalizedScope) && 'data' in normalizedScope) {
            normalizedScope.item = normalizedScope.data;
        }

        if (!('column' in normalizedScope) && 'columnDefinition' in normalizedScope) {
            normalizedScope.column = normalizedScope.columnDefinition;
        }

        return normalizedScope;
    }

    return {
        forwardedSlotNames,
        getLegacyPreviewSlotName,
        hasLegacyPreviewSlot,
        getSlotRecord,
        normalizeInlineEditSlotScope,
        normalizeLegacyPreviewSlotScope,
        openDetailFromSlotScope,
        normalizeForwardedSlotScope,
    };
}
