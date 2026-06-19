/**
 * @sw-package framework
 */

/* eslint-disable sw-deprecation-rules/private-feature-declarations */

import type {
    SwMeteorEntityDataTableRecord,
    SwMeteorEntityDataTableRecords,
} from './sw-meteor-entity-data-table.internal-types';

export function toArray<TValue>(value: TValue | TValue[]): TValue[] {
    return Array.isArray(value) ? value : [value];
}

export function isTableRecord(value: unknown): value is SwMeteorEntityDataTableRecord {
    return typeof value === 'object' && value !== null && 'id' in value && typeof (value as { id: unknown }).id === 'string';
}

export function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

export function resolveTotal(records: SwMeteorEntityDataTableRecords): number {
    if ('total' in records && typeof records.total === 'number') {
        return records.total;
    }

    return records.length;
}
