/**
 * @sw-package framework
 *
 * @deprecated tag:v6.9.0 - Will be removed together with the legacy compatibility mode.
 */

import { isLegacyCompatRequest, legacyParamsSerializer, toLegacyQueryString } from 'src/core/factory/http-legacy-compat';

describe('core/factory/http-legacy-compat.ts', () => {
    describe('legacyParamsSerializer', () => {
        it.each([
            ['array values', { ids: [1, 2] }, 'ids[]=1&ids[]=2'],
            ['nested objects', { filter: { a: 'b' } }, 'filter[a]=b'],
            ['deeply nested objects', { a: { b: { c: 1 } } }, 'a[b][c]=1'],
            ['arrays of objects', { f: [{ t: 'eq' }] }, 'f[0][t]=eq'],
            ['the characters Axios 0.x left literal', { q: 'a,b:c$d[e]f' }, 'q=a,b:c$d[e]f'],
            ['characters that stay encoded', { q: 'a b&c=d' }, 'q=a+b%26c%3Dd'],
            ['skipped null and undefined values', { a: null, b: undefined, c: '' }, 'c='],
        ])('should serialize %s the way Axios 0.x did', (_name, params, expected) => {
            expect(legacyParamsSerializer(params)).toBe(expected);
        });

        it('should serialize dates as ISO strings', () => {
            expect(legacyParamsSerializer({ d: new Date(Date.UTC(2020, 0, 1)) })).toBe('d=2020-01-01T00:00:00.000Z');
        });
    });

    describe('toLegacyQueryString', () => {
        it('should decode the characters Axios 0.x left literal', () => {
            expect(toLegacyQueryString('a%5B0%5D=x%3Ay%24z%2Cw')).toBe('a[0]=x:y$z,w');
        });

        it('should leave every other escape sequence untouched', () => {
            expect(toLegacyQueryString('q=a+b%26c%3Dd%2Fe')).toBe('q=a+b%26c%3Dd%2Fe');
        });
    });

    describe('isLegacyCompatRequest', () => {
        // The flag is driven explicitly instead of through `it.activeFeatureFlags()`, because the
        // major lanes run the whole suite with `FEATURE_ALL` set and the helper cannot deactivate a flag.
        const withV68 = (active: boolean) =>
            jest.spyOn(Shopware.Feature, 'isActive').mockImplementation((flag) => flag === 'V6_8_0_0' && active);

        afterEach(() => {
            jest.restoreAllMocks();
        });

        it('should enable the compatibility mode while V6_8_0_0 is inactive', () => {
            withV68(false);

            expect(isLegacyCompatRequest({})).toBe(true);
        });

        it('should disable the compatibility mode once V6_8_0_0 is active', () => {
            withV68(true);

            expect(isLegacyCompatRequest({})).toBe(false);
        });

        it.each([false, true])(
            'should let an explicit useAxiosV1 win over the V6_8_0_0 flag (flag active: %s)',
            (active) => {
                withV68(active);

                expect(isLegacyCompatRequest({ useAxiosV1: true })).toBe(false);
                expect(isLegacyCompatRequest({ useAxiosV1: false })).toBe(true);
            },
        );
    });
});
