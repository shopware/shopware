/**
 * @sw-package framework
 */

import {
    getActiveRouteNames,
    entryParamsMatchRoute,
    isEntryOnActiveRoute,
} from 'src/app/component/structure/sw-admin-menu-item/menu-item-active.helper';

describe('src/app/component/structure/sw-admin-menu-item/menu-item-active.helper', () => {
    describe('getActiveRouteNames', () => {
        it('collects the route names of the resolved chain', () => {
            const route = {
                matched: [
                    { name: 'core' },
                    { name: 'sw.extension.my-extensions' },
                    { name: 'sw.extension.my-extensions.listing' },
                ],
            };

            expect(getActiveRouteNames(route)).toEqual(
                new Set([
                    'core',
                    'sw.extension.my-extensions',
                    'sw.extension.my-extensions.listing',
                ]),
            );
        });

        it('is empty for a route without a matched chain', () => {
            expect(getActiveRouteNames({})).toEqual(new Set());
            expect(getActiveRouteNames(undefined)).toEqual(new Set());
        });

        it('ignores records without a name', () => {
            const route = {
                matched: [
                    { name: 'core' },
                    {},
                    { name: undefined },
                    { name: 'sw.product.index' },
                ],
            };

            expect(getActiveRouteNames(route)).toEqual(
                new Set([
                    'core',
                    'sw.product.index',
                ]),
            );
        });

        it('bridges sibling detail pages to their owning nav route via the parentPath chain (multi-hop)', () => {
            // sw.settings.payment.detail → parentPath sw.settings.payment.overview → parentPath sw.settings.index
            const route = {
                name: 'sw.settings.payment.detail',
                matched: [{ name: 'sw.settings.payment.detail' }],
                meta: { parentPath: 'sw.settings.payment.overview' },
            };
            const router = {
                getRoutes: () => [
                    { name: 'sw.settings.payment.overview', meta: { parentPath: 'sw.settings.index' } },
                    { name: 'sw.settings.index', meta: {} },
                ],
            };

            const names = getActiveRouteNames(route, router);

            expect(names.has('sw.settings.payment.detail')).toBe(true);
            expect(names.has('sw.settings.payment.overview')).toBe(true);
            expect(names.has('sw.settings.index')).toBe(true);
        });

        it('bridges a module route to its own menu entry when no parentPath is declared', () => {
            // A plugin detail route sits next to the nav target instead of below it, so `matched` cannot reach it
            const route = {
                name: 'sw.foo.detail',
                matched: [{ name: 'sw.foo.detail' }],
                meta: { $module: { navigation: [{ path: 'sw.foo.index' }] } },
            };

            expect(getActiveRouteNames(route)).toEqual(
                new Set([
                    'sw.foo.detail',
                    'sw.foo.index',
                ]),
            );
        });

        it('continues the parentPath walk from the module navigation entry', () => {
            const route = {
                name: 'sw.foo.detail',
                matched: [{ name: 'sw.foo.detail' }],
                meta: { $module: { navigation: [{ path: 'sw.foo.index' }] } },
            };
            const router = { getRoutes: () => [{ name: 'sw.foo.index', meta: { parentPath: 'sw.settings.index' } }] };

            const names = getActiveRouteNames(route, router);

            // The detail page has to reach the same ancestors as the module's own list page
            expect(names.has('sw.foo.index')).toBe(true);
            expect(names.has('sw.settings.index')).toBe(true);
        });

        it('prefers a declared parentPath over the module navigation entry', () => {
            const route = {
                name: 'sw.foo.detail',
                matched: [{ name: 'sw.foo.detail' }],
                meta: {
                    parentPath: 'sw.foo.overview',
                    $module: { navigation: [{ path: 'sw.foo.index' }] },
                },
            };

            expect(getActiveRouteNames(route)).toEqual(
                new Set([
                    'sw.foo.detail',
                    'sw.foo.overview',
                ]),
            );
        });

        it('does not guess for a core module contributing several menu entries', () => {
            // sw-extension contributes both "Store" and "My extensions"; core declares parentPath instead
            const route = {
                name: 'sw.extension.module',
                matched: [{ name: 'sw.extension.module' }],
                meta: {
                    $module: {
                        type: 'core' as const,
                        navigation: [
                            { path: 'sw.extension.store' },
                            { path: 'sw.extension.my-extensions' },
                        ],
                    },
                },
            };

            expect(getActiveRouteNames(route)).toEqual(new Set(['sw.extension.module']));
        });

        it('bridges an extension module contributing several menu entries', () => {
            // An extension cannot be asked to add parentPath after the fact, so its entries are used as-is
            const route = {
                name: 'sw.foo.detail',
                matched: [{ name: 'sw.foo.detail' }],
                meta: {
                    $module: {
                        navigation: [
                            { path: 'sw.foo.index' },
                            { path: 'sw.foo.reports' },
                        ],
                    },
                },
            };

            expect(getActiveRouteNames(route)).toEqual(
                new Set([
                    'sw.foo.detail',
                    'sw.foo.index',
                    'sw.foo.reports',
                ]),
            );
        });

        it('does not add sibling menu entries to a route the matched chain already anchors', () => {
            // sw-product-reviews contributes five entries and every route is one of them
            const route = {
                name: 'sw.product.reviews.pending',
                matched: [{ name: 'sw.product.reviews.pending' }],
                meta: {
                    $module: {
                        navigation: [
                            { path: 'sw.product.reviews.index' },
                            { path: 'sw.product.reviews.pending' },
                        ],
                    },
                },
            };

            expect(getActiveRouteNames(route)).toEqual(new Set(['sw.product.reviews.pending']));
        });

        it('tolerates a module without a usable navigation entry', () => {
            const withoutPath = {
                name: 'sw.foo.detail',
                matched: [{ name: 'sw.foo.detail' }],
                meta: { $module: { navigation: [{ path: undefined }] } },
            };

            expect(getActiveRouteNames({ name: 'a', meta: { $module: {} } })).toEqual(new Set(['a']));
            expect(getActiveRouteNames({ name: 'a', meta: { $module: { navigation: [] } } })).toEqual(new Set(['a']));
            expect(getActiveRouteNames(withoutPath)).toEqual(new Set(['sw.foo.detail']));
        });

        it('does not loop on a cyclic parentPath chain', () => {
            const route = { name: 'a', matched: [{ name: 'a' }], meta: { parentPath: 'b' } };
            const router = {
                getRoutes: () => [
                    { name: 'b', meta: { parentPath: 'a' } },
                    { name: 'a', meta: { parentPath: 'b' } },
                ],
            };

            expect(getActiveRouteNames(route, router)).toEqual(
                new Set([
                    'a',
                    'b',
                ]),
            );
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
            // The reported bug: a path-less parent above an ancestor of the deep route
            const route = {
                matched: [
                    { name: 'sw.extension.my-extensions' },
                    { name: 'sw.extension.my-extensions.listing' },
                ],
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

        it('lights a plugin leaf and its path-less group through the module navigation entry', () => {
            const route = {
                name: 'sw.foo.detail',
                matched: [{ name: 'sw.foo.detail' }],
                meta: { $module: { navigation: [{ path: 'sw.foo.index' }] } },
                params: {},
            };
            const catalogue = {
                id: 'sw-catalogue',
                children: [{ id: 'sw-foo', path: 'sw.foo.index', children: [] }],
            };
            const activeNames = getActiveRouteNames(route);

            expect(isEntryOnActiveRoute(catalogue.children[0], route, activeNames)).toBe(true);
            expect(isEntryOnActiveRoute(catalogue, route, activeNames)).toBe(true);
        });

        it('lights a leaf reachable only through the parentPath bridge', () => {
            const route = {
                name: 'sw.product.detail.base',
                matched: [
                    { name: 'sw.product.detail' },
                    { name: 'sw.product.detail.base' },
                ],
                meta: { parentPath: 'sw.product.index' },
                params: {},
            };
            const router = { getRoutes: () => [{ name: 'sw.product.index', meta: {} }] };
            const activeNames = getActiveRouteNames(route, router);

            expect(isEntryOnActiveRoute({ path: 'sw.product.index' }, route, activeNames)).toBe(true);
        });
    });
});
