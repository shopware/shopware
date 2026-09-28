/**
 * @sw-package framework
 */

import * as VueRouter from 'vue-router';

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

    describe('navigation guard', () => {
        const dummyComponent = { template: '<div></div>' };

        function createRouter(loggedIn) {
            const loginService = {
                isLoggedIn: () => loggedIn,
                isRefreshing: () => Promise.resolve(false),
                refreshToken: () => Promise.reject(new Error('not logged in')),
            };

            const factory = new Shopware.Classes._private.RouterFactory(
                VueRouter,
                undefined,
                Application.getContainer('factory').module,
                loginService,
            );

            factory.addRoutes([
                { path: '/', name: 'core', component: dummyComponent },
                { path: '/login', name: 'sw.login.index', component: dummyComponent },
                { path: '/sw/dashboard/index', name: 'sw.dashboard.index', component: dummyComponent },
                { path: '/oauth/authorize', name: 'sw.oauth.authorize.index', component: dummyComponent },
            ]);

            return factory.createRouterInstance();
        }

        beforeAll(() => {
            Shopware.Service().register('userActivityService', () => ({
                updateLastUserActivity: () => {},
            }));
        });

        beforeEach(() => {
            sessionStorage.clear();
        });

        it('should restore the route requested before the SSO login', async () => {
            const oauthPath = '/oauth/authorize?client_id=shopware-cli&state=abc';

            // Unauthenticated request of the consent page, the guard remembers the route
            const loggedOutRouter = createRouter(false);
            await loggedOutRouter.push(oauthPath);
            expect(loggedOutRouter.currentRoute.value.name).toBe('sw.login.index');

            // The SSO login is started and the browser returns to the plain Administration URL
            sessionStorage.setItem('sw-sso-restore-previous-route', 'true');
            const loggedInRouter = createRouter(true);
            await loggedInRouter.push('/sw/dashboard/index');

            expect(loggedInRouter.currentRoute.value.fullPath).toBe(oauthPath);
            expect(sessionStorage.getItem('sw-sso-restore-previous-route')).toBeNull();
            expect(sessionStorage.getItem('sw-admin-previous-route')).toBeNull();
        });

        it('should restore the previous route only once', async () => {
            sessionStorage.setItem('sw-sso-restore-previous-route', 'true');
            sessionStorage.setItem('sw-admin-previous-route', JSON.stringify({ fullPath: '/oauth/authorize?state=abc' }));

            const router = createRouter(true);
            await router.push('/sw/dashboard/index');
            await router.push('/sw/dashboard/index');

            expect(router.currentRoute.value.fullPath).toBe('/sw/dashboard/index');
        });

        it('should not restore the previous route without a SSO login', async () => {
            sessionStorage.setItem('sw-admin-previous-route', JSON.stringify({ fullPath: '/oauth/authorize?state=abc' }));

            const router = createRouter(true);
            await router.push('/sw/dashboard/index');

            expect(router.currentRoute.value.fullPath).toBe('/sw/dashboard/index');
        });

        it('should keep the restore flag while the user is not logged in', async () => {
            sessionStorage.setItem('sw-sso-restore-previous-route', 'true');

            const router = createRouter(false);
            await router.push('/login');

            expect(sessionStorage.getItem('sw-sso-restore-previous-route')).toBe('true');
        });

        it('should continue the navigation when the stored previous route is invalid', async () => {
            sessionStorage.setItem('sw-sso-restore-previous-route', 'true');
            sessionStorage.setItem('sw-admin-previous-route', '{invalid');

            const router = createRouter(true);
            await router.push('/sw/dashboard/index');

            expect(router.currentRoute.value.fullPath).toBe('/sw/dashboard/index');
            expect(sessionStorage.getItem('sw-admin-previous-route')).toBeNull();
        });
    });
});
