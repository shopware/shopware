/**
 * @sw-package framework
 */

import * as VueRouter from 'vue-router';
import coreRoutes from 'src/app/route';

const { Module, Application } = Shopware;

describe('core/factory/router.factory.js', () => {
    function createRouterFactory() {
        return new Shopware.Classes._private.RouterFactory(undefined, undefined, Application.getContainer('factory').module);
    }

    beforeEach(() => {
        Module.getModuleRegistry().clear();

        document.head.innerHTML =
            '<link rel="icon" type="image/svg+xml" sizes="any" href="administration/static/img/favicon/favicon.svg" id="dynamic-favicon">';
    });

    describe('_setModuleFavicon', () => {
        it('should set an SVG module favicon with matching type and sizes', () => {
            Module.register('sw-product', {
                favicon: 'icon-module-products.svg',
                routes: {
                    index: {
                        component: 'sw-product-list',
                        path: 'index',
                    },
                },
            });

            const factory = createRouterFactory();
            const result = factory._setModuleFavicon({ name: 'sw.product.index', meta: {} }, '/bundles/');

            const favicon = document.getElementById('dynamic-favicon');
            expect(result).toBe(true);
            expect(favicon.rel).toBe('icon');
            expect(favicon.type).toBe('image/svg+xml');
            expect(favicon.getAttribute('sizes')).toBe('any');
            expect(favicon.getAttribute('href')).toBe(
                '/bundles/administration/administration/static/img/favicon/modules/icon-module-products.svg',
            );
        });

        it('should fall back to the Shopware logo when the module has no favicon', () => {
            Module.register('sw-foo', {
                routes: {
                    index: {
                        component: 'sw-foo-bar',
                        path: 'index',
                    },
                },
            });

            const factory = createRouterFactory();
            const result = factory._setModuleFavicon({ name: 'sw.foo.index', meta: {} }, '/bundles/');

            const favicon = document.getElementById('dynamic-favicon');
            expect(result).toBe(true);
            expect(favicon.rel).toBe('icon');
            expect(favicon.type).toBe('image/svg+xml');
            expect(favicon.getAttribute('sizes')).toBe('any');
            expect(favicon.getAttribute('href')).toBe(
                '/bundles/administration/administration/static/img/favicon/favicon.svg',
            );
        });

        it('should serve the Shopware logo from the Administration even when the module sets a faviconSrc', () => {
            Module.register('sw-plugin-without-icon', {
                faviconSrc: 'myplugin',
                routes: {
                    index: {
                        component: 'sw-plugin-list',
                        path: 'index',
                    },
                },
            });

            const factory = createRouterFactory();
            const result = factory._setModuleFavicon({ name: 'sw.plugin.without.icon.index', meta: {} }, '/bundles/');

            const favicon = document.getElementById('dynamic-favicon');
            expect(result).toBe(true);
            expect(favicon.getAttribute('href')).toBe(
                '/bundles/administration/administration/static/img/favicon/favicon.svg',
            );
        });

        it('should keep the PNG type for plugin favicons served from a custom faviconSrc', () => {
            Module.register('sw-plugin', {
                favicon: 'my-plugin-icon.png',
                faviconSrc: 'myplugin',
                routes: {
                    index: {
                        component: 'sw-plugin-list',
                        path: 'index',
                    },
                },
            });

            const factory = createRouterFactory();
            const result = factory._setModuleFavicon({ name: 'sw.plugin.index', meta: {} }, '/bundles/');

            const favicon = document.getElementById('dynamic-favicon');
            expect(result).toBe(true);
            expect(favicon.type).toBe('image/png');
            // A plugin PNG can be any size, so no size is claimed.
            expect(favicon.getAttribute('sizes')).toBeNull();
            expect(favicon.getAttribute('href')).toBe(
                '/bundles/myplugin/administration/static/img/favicon/modules/my-plugin-icon.png',
            );
        });

        it('should reset to the Shopware logo when the route belongs to no module', () => {
            Module.register('sw-product', {
                favicon: 'icon-module-products.svg',
                routes: {
                    index: {
                        component: 'sw-product-list',
                        path: 'index',
                    },
                },
            });

            const factory = createRouterFactory();
            factory._setModuleFavicon({ name: 'sw.product.index', meta: {} }, '/bundles/');

            const result = factory._setModuleFavicon({ name: 'sw.unknown.index', meta: {} }, '/bundles/');

            const favicon = document.getElementById('dynamic-favicon');
            expect(result).toBe(false);
            expect(favicon.type).toBe('image/svg+xml');
            expect(favicon.getAttribute('sizes')).toBe('any');
            expect(favicon.getAttribute('href')).toBe(
                '/bundles/administration/administration/static/img/favicon/favicon.svg',
            );
        });

        it('should not fail when the dynamic favicon link is missing', () => {
            document.head.innerHTML = '';

            const factory = createRouterFactory();
            const result = factory._setModuleFavicon({ name: 'sw.unknown.index', meta: {} }, '/bundles/');

            expect(result).toBe(false);
        });

        it('should rewrite the href when switching between two SVG module favicons', () => {
            Module.register('sw-product', {
                favicon: 'icon-module-products.svg',
                routes: {
                    index: {
                        component: 'sw-product-list',
                        path: 'index',
                    },
                },
            });
            Module.register('sw-order', {
                favicon: 'icon-module-orders.svg',
                routes: {
                    index: {
                        component: 'sw-order-list',
                        path: 'index',
                    },
                },
            });

            const factory = createRouterFactory();
            factory._setModuleFavicon({ name: 'sw.product.index', meta: {} }, '/bundles/');

            const favicon = document.getElementById('dynamic-favicon');
            const setAttribute = jest.spyOn(favicon, 'setAttribute');

            factory._setModuleFavicon({ name: 'sw.order.index', meta: {} }, '/bundles/');

            expect(setAttribute.mock.calls.map(([name]) => name)).toEqual(['href']);
            expect(favicon.getAttribute('href')).toBe(
                '/bundles/administration/administration/static/img/favicon/modules/icon-module-orders.svg',
            );
        });
    });

    describe('createRouterInstance', () => {
        beforeEach(() => {
            Application.view.getComponentForRoute = jest.fn((name) => ({
                name,
                template: '<div></div>',
            }));
        });

        afterEach(() => {
            delete Application.view.getComponentForRoute;
        });

        function createRouter({ loggedIn = true } = {}) {
            const factory = new Shopware.Classes._private.RouterFactory(
                VueRouter,
                undefined,
                Application.getContainer('factory').module,
                { isLoggedIn: () => loggedIn },
            );

            // The factory mutates the route definitions, so every router gets its own copy
            factory.addRoutes(structuredClone(coreRoutes));
            factory.addModuleRoutes([
                {
                    path: '/sw/dashboard/index',
                    name: 'sw.dashboard.index',
                    component: 'sw-dashboard-index',
                },
                {
                    path: '/login',
                    name: 'sw.login.index',
                    component: 'sw-login',
                    coreRoute: true,
                },
            ]);

            return factory.createRouterInstance();
        }

        it('should resolve unknown URLs to the not found page inside the root route', () => {
            const router = createRouter();
            const route = router.resolve('/sw/does-not/exist');

            expect(route.name).toBe('sw.not.found.index');
            expect(route.matched.map((record) => record.name)).toEqual([
                'core',
                'sw.not.found.index',
            ]);
            expect(route.matched[1].components.default.name).toBe('sw-not-found');
        });

        it('should prefer existing routes over the not found page', () => {
            const router = createRouter();

            expect(router.resolve('/sw/dashboard/index').matched.map((record) => record.name)).toEqual([
                'core',
                'sw.dashboard.index',
            ]);
            expect(router.resolve('/').name).toBe('core');
            expect(router.resolve('/login').name).toBe('sw.login.index');
            expect(router.resolve('/error').name).toBe('error');
        });

        it('should not register the not found page for the login-only boot', () => {
            const router = createRouter({ loggedIn: false });

            expect(router.hasRoute('sw.not.found.index')).toBe(false);
            expect(router.resolve('/sw/dashboard/index').matched.map((record) => record.name)).toEqual([
                'core',
                'sw.dashboard.index',
            ]);
        });
    });
});
