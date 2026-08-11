/**
 * @sw-package inventory
 */
import { parseClauses, isFieldClause } from './explain.helper';

describe('src/module/sw-settings-search/helper/explain.helper', () => {
    describe('parseClauses', () => {
        it('returns an empty list for a missing map', () => {
            expect(parseClauses(undefined)).toEqual([]);
            expect(parseClauses(null)).toEqual([]);
        });

        it('parses each clause name and pairs it with its numeric score', () => {
            const clause = JSON.stringify({ field: 'name', term: 'iron' });

            expect(parseClauses({ [clause]: 12.5 })).toEqual([{ parsed: { field: 'name', term: 'iron' }, score: 12.5 }]);
        });

        it('skips keys that are not valid JSON and defaults an unparsable score to 0', () => {
            const valid = JSON.stringify({ field: 'name' });

            expect(parseClauses({ 'not json': 1, [valid]: 'x' })).toEqual([{ parsed: { field: 'name' }, score: 0 }]);
        });
    });

    describe('isFieldClause', () => {
        it('treats a plain field clause as a field clause', () => {
            expect(isFieldClause({ field: 'name', term: 'iron' })).toBe(true);
        });

        it('excludes boost and cross-entity clauses by key presence, including a boost of 0', () => {
            expect(isFieldClause({ boost: 5, name: 'rule' })).toBe(false);
            expect(isFieldClause({ boost: 0, name: 'rule' })).toBe(false);
            expect(isFieldClause({ crossEntity: 'category', term: 'iron' })).toBe(false);
        });

        it('never treats a non-object clause as a field clause', () => {
            expect(isFieldClause(0)).toBe(false);
            expect(isFieldClause('name')).toBe(false);
            expect(isFieldClause(null)).toBe(false);
        });
    });
});
