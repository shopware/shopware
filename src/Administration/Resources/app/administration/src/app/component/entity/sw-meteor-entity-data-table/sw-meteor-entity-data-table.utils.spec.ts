/**
 * @sw-package framework
 */

import { isRecord, isTableRecord, resolveTotal, toArray } from './sw-meteor-entity-data-table.utils';
import type { SwMeteorEntityDataTableRecords } from './sw-meteor-entity-data-table.internal-types';

describe('src/app/component/entity/sw-meteor-entity-data-table/sw-meteor-entity-data-table.utils', () => {
    it('normalizes scalar values to arrays', () => {
        expect(toArray('name')).toEqual(['name']);
        expect(
            toArray([
                'name',
                'link',
            ]),
        ).toEqual([
            'name',
            'link',
        ]);
    });

    it('identifies table records by string id', () => {
        expect(isTableRecord({ id: 'record-id' })).toBe(true);
        expect(isTableRecord({ id: 1 })).toBe(false);
        expect(isTableRecord(null)).toBe(false);
    });

    it('checks plain record-like objects', () => {
        expect(isRecord({ value: true })).toBe(true);
        expect(isRecord([])).toBe(false);
        expect(isRecord(null)).toBe(false);
    });

    it('resolves collection totals before falling back to length', () => {
        const recordsWithTotal = Object.assign([{ id: 'record-1' }], { total: 10 });
        const recordsWithoutTotal = [{ id: 'record-1' }];

        expect(resolveTotal(recordsWithTotal as unknown as SwMeteorEntityDataTableRecords)).toBe(10);
        expect(resolveTotal(recordsWithoutTotal as unknown as SwMeteorEntityDataTableRecords)).toBe(1);
    });
});
