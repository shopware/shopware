/**
 * @sw-package framework
 */
import overrideComponentRegisterPlugin from './index';
import { parse } from '@babel/parser';

/**
 * The plugin hooks are typed as Vite's `ObjectHook` unions, which aren't directly callable in tests.
 * This narrows the returned object to the plain-function shape the plugin actually provides.
 */
type CallableOverridePlugin = {
    name: string;
    transform(code: string, id: string): { code: string } | null;
};

function createPlugin(): CallableOverridePlugin {
    return overrideComponentRegisterPlugin({ pluginEntryFile: 'plugin' }) as unknown as CallableOverridePlugin;
}

describe('build/vite-plugins/override-component-register', () => {
    it('is named so Vite can identify it', () => {
        expect(createPlugin().name).toBe('shopware-vite-plugin-override-component');
    });

    it('leaves every module but the entry untouched', () => {
        expect(createPlugin().transform('code', 'other')).toBeNull();
    });

    it('registers every override below the Vite root before the entry code', () => {
        const entryCode = "import { createApp } from 'vue';\ncreateApp({}).mount('#app');";

        const result = createPlugin().transform(entryCode, 'plugin');

        expect(result?.code).toContain("import.meta.glob('/**/*.override.vue', { eager: true, import: 'default' })");
        expect(result?.code).toContain('Shopware.Component.registerOverrideComponent(component)');
        expect(result?.code.trimEnd().endsWith(entryCode)).toBe(true);
        expect(() => parse(result!.code, { sourceType: 'module' })).not.toThrow();
    });
});
