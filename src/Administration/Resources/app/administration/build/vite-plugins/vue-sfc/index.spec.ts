/**
 * @sw-package framework
 */

import vue from '@vitejs/plugin-vue';
import vueSfcPlugins from './index';

// The real plugin pulls in Vite's Node API, which cannot load under jsdom. The real pipeline is
// built by the shopware-setup sourcemap fixture.
jest.mock('@vitejs/plugin-vue', () => jest.fn(() => ({ name: 'vite:vue' })));

describe('build/vite-plugins/vue-sfc', () => {
    beforeEach(() => {
        jest.mocked(vue).mockClear();
    });

    it('runs the Shopware setup transform ahead of @vitejs/plugin-vue', () => {
        const plugins = vueSfcPlugins();

        expect(plugins.map((plugin) => plugin.name)).toEqual(['shopware-vite-plugin-shopware-setup', 'vite:vue']);
        expect(plugins[0].enforce).toBe('pre');
    });

    it('compiles core templates without Vue 2 compat', () => {
        vueSfcPlugins();

        expect(vue).toHaveBeenCalledWith({});
    });

    it('compiles extension templates with Vue 2 compat when asked to', () => {
        vueSfcPlugins({ vue2TemplateCompat: true });

        expect(vue).toHaveBeenCalledWith({ template: { compilerOptions: { compatConfig: { MODE: 2 } } } });
    });
});
