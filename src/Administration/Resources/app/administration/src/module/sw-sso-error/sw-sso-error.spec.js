/**
 * @sw-package framework
 */

import 'src/module/sw-sso-error';

const { Module, Component } = Shopware;

describe('src/module/sw-sso-error', () => {
    it('should be available', () => {
        const module = Module.getModuleRegistry().get('sw-sso-error');
        expect(module).toBeTruthy();

        const routes = module.routes;
        expect(routes.size).toBe(1);
    });

    it('should register the sub component', () => {
        const components = Component.getComponentRegistry();
        expect(components.has('sw-sso-error-index')).toBe(true);
    });
});
