/**
 * @sw-package framework
 */

import 'src/module/sw-oauth-authorize';

const { Module, Component } = Shopware;

describe('src/module/sw-oauth-authorize', () => {
    it('should register the module with a single core route', () => {
        const module = Module.getModuleRegistry().get('sw-oauth-authorize');
        expect(module).toBeTruthy();

        const routes = module?.routes;
        expect(routes?.size).toBe(1);

        const indexRoute = routes?.get('sw.oauth.authorize.index');
        expect(indexRoute).toBeTruthy();
        expect(indexRoute?.path).toBe('/oauth/authorize');
        expect(indexRoute?.coreRoute).toBe(true);
        expect(indexRoute).not.toHaveProperty('meta.forceRoute');
    });

    it('should register the page component', () => {
        const components = Component.getComponentRegistry();
        expect(components.has('sw-oauth-authorize-index')).toBe(true);
    });
});
