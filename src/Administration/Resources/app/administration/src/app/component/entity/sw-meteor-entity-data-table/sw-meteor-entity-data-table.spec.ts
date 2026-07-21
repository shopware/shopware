/**
 * @sw-package framework
 */

import type {
    MeteorEntityTableRecord,
    MeteorEntityTableResult,
    MeteorEntityTableState,
} from './sw-meteor-entity-data-table.types';
import { getRecordsFromResult, getStateSnapshot, getTotalFromResult } from './sw-meteor-entity-data-table.utils';

describe('sw-meteor-entity-data-table helpers', () => {
    it('normalizes search result records and totals', () => {
        const records: MeteorEntityTableRecord[] = [
            { id: 'manufacturer-1', name: 'Shopware' },
            { id: 'manufacturer-2', name: 'Meteor' },
        ];
        const result = Object.assign([...records], { total: 8 }) as MeteorEntityTableResult;

        expect(getRecordsFromResult(result)).toEqual(records);
        expect(getTotalFromResult(result, records)).toBe(8);
    });

    it('falls back to the loaded record count when no total is available', () => {
        const records: MeteorEntityTableRecord[] = [{ id: 'manufacturer-1' }];
        const result = [...records] as MeteorEntityTableResult;

        expect(getTotalFromResult(result, records)).toBe(1);
    });

    it('returns a serializable state snapshot', () => {
        const state: MeteorEntityTableState = {
            page: 2,
            limit: 50,
            searchTerm: 'shop',
            sortBy: 'name',
            sortDirection: 'ASC',
            naturalSorting: true,
        };

        expect(getStateSnapshot(state)).toEqual(state);
        expect(getStateSnapshot(state)).not.toBe(state);
    });
});
