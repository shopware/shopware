/**
 * @sw-package framework
 */

import {
    getMatchedRouteNames,
    entryParamsMatchRoute,
    isEntryOnActiveRoute,
} from 'src/app/component/structure/sw-admin-menu-item/menu-item-active.helper';

describe('src/app/component/structure/sw-admin-menu-item/menu-item-active.helper', () => {
    describe('getMatchedRouteNames', () => {
        it('collects the route names of the resolved chain', () => {
            const route = {
                matched: [{ name: 'core' }, { name: 'sw.extension.my-extensions' }, { name: 'sw.extension.my-extensions.listing' }],
            };

            expect(getMatchedRouteNames(route)).toEqual(
                new Set(['core', 'sw.extension.my-extensions', 'sw.extension.my-extensions.listing']),
            );
        });

        it('is empty for a route without a matched chain', () => {
            expect(getMatchedRouteNames({})).toEqual(new Set());
            expect(getMatchedRouteNames(undefined)).toEqual(new Set());
        });

        it('ignores records without a name', () => {
            const route = { matched: [{ name: 'core' }, {}, { name: undefined }, { name: 'sw.product.index' }] };

            expect(getMatchedRouteNames(route)).toEqual(new Set(['core', 'sw.product.index']));
        });
    });

    describe('entryParamsMatchRoute', () => {
        it('matches when the entry declares no params', () => {
            expect(entryParamsMatchRoute({ path: 'sw.product.index' }, { params: {} })).toBe(true);
        });

        it('matches only when every declared param equals the route param', () => {
            const route = { params: { id: 'abc' } };

            expect(entryParamsMatchRoute({ params: { id: 'abc' } }, route)).toBe(true);
            expect(entryParamsMatchRoute({ params: { id: 'xyz' } }, route)).toBe(false);
        });

        it('compares params as strings (uuid / numeric parity)', () => {
            expect(entryParamsMatchRoute({ params: { id: 5 } }, { params: { id: '5' } })).toBe(true);
        });
    });

    describe('isEntryOnActiveRoute', () => {
        it('lights a leaf whose route is in the matched chain', () => {
            const route = { matched: [{ name: 'sw.product.index' }], params: {} };

            expect(isEntryOnActiveRoute({ path: 'sw.product.index' }, route)).toBe(true);
        });

        it('does not light a leaf whose route is not in the matched chain', () => {
            const route = { matched: [{ name: 'sw.order.index' }], params: {} };

            expect(isEntryOnActiveRoute({ path: 'sw.product.index' }, route)).toBe(false);
        });

        it('lights a path-less parent when a descendant route is in the matched chain', () => {
            // The reported bug: "Extensions" (no path) sits above `sw.extension.my-extensions`,
            // which is an ancestor of the deep route `…my-extensions.listing.app`.
            const route = {
                matched: [{ name: 'sw.extension.my-extensions' }, { name: 'sw.extension.my-extensions.listing' }],
                params: {},
            };
            const extensions = {
                id: 'sw-extension',
                children: [
                    { id: 'sw-extension-store', path: 'sw.extension.store', children: [] },
                    { id: 'sw-extension-my-extensions', path: 'sw.extension.my-extensions', children: [] },
                ],
            };

            expect(isEntryOnActiveRoute(extensions, route)).toBe(true);
        });

        it('does not light a path-less parent when no descendant is active', () => {
            const route = { matched: [{ name: 'sw.order.index' }], params: {} };
            const extensions = {
                id: 'sw-extension',
                children: [{ id: 'sw-extension-store', path: 'sw.extension.store', children: [] }],
            };

            expect(isEntryOnActiveRoute(extensions, route)).toBe(false);
        });

        it('disambiguates entries that share a route name by their params (SDK / app modules)', () => {
            // Many SDK items live on `sw.extension.sdk.index`, differing only by `params.id`.
            const route = { matched: [{ name: 'sw.extension.sdk.index' }], params: { id: 'module-a' } };
            const entryA = { id: 'a', path: 'sw.extension.sdk.index', params: { id: 'module-a' }, children: [] };
            const entryB = { id: 'b', path: 'sw.extension.sdk.index', params: { id: 'module-b' }, children: [] };

            expect(isEntryOnActiveRoute(entryA, route)).toBe(true);
            expect(isEntryOnActiveRoute(entryB, route)).toBe(false);
        });
    });
});
