/**
 * @sw-package framework
 */
import path from 'path';
import { parse } from '@babel/parser';
import { SourceMapConsumer, type RawSourceMap } from 'source-map-js';
import overrideComponentRegisterPlugin from './index';

type CallableOverridePlugin = {
    name: string;
    configResolved(): void;
    transform(code: string, id: string): { code: string; map: RawSourceMap } | null;
};

function createPlugin(): CallableOverridePlugin {
    const plugin = overrideComponentRegisterPlugin({
        root: path.resolve(__dirname, './_fixtures'),
        pluginEntryFile: 'plugin',
    }) as unknown as CallableOverridePlugin;

    plugin.configResolved();

    return plugin;
}

describe('build/vite-plugins/override-component-register', () => {
    it('leaves every module but the entry alone', () => {
        expect(createPlugin().transform('code', 'other')).toBeNull();
    });

    it('leaves the entry alone while no overrides are known', () => {
        const plugin = overrideComponentRegisterPlugin({
            root: path.resolve(__dirname, './_fixtures'),
            pluginEntryFile: 'plugin',
        }) as unknown as CallableOverridePlugin;

        expect(plugin.transform('code', 'plugin')).toBeNull();
    });

    it('imports every override and registers it for the hidden mount', () => {
        const result = createPlugin().transform('// entry', 'plugin');

        expect(result?.code).toContain(
            "from './build/vite-plugins/override-component-register/_fixtures/sw-setup-example.override.vue';",
        );
        expect(result?.code).toMatch(/import _swOverride\d+ from/);
        expect(result?.code).toMatch(/Shopware\.Component\.registerOverrideComponent\(_swOverride\d+\);/);
        expect(result?.code).toMatch(/\/\/ entry$/);
    });

    it('registers same-named overrides from different directories with distinct bindings', () => {
        const result = createPlugin().transform('// entry', 'plugin');

        expect(result!.code).toContain('first/sw-same-name.override.vue');
        expect(result!.code).toContain('second/sw-same-name.override.vue');
        // A repeated binding would be a SyntaxError.
        expect(() => parse(result!.code, { sourceType: 'module' })).not.toThrow();
    });

    it('emits a stable order so the generated entry does not depend on filesystem walk order', () => {
        const plugin = createPlugin();
        const first = plugin.transform('// entry', 'plugin');

        expect(plugin.transform('// entry', 'plugin')!.code).toBe(first!.code);

        const paths = [...first!.code.matchAll(/import _swOverride\d+ from '\.\/(.+?)';/g)].map((match) => match[1]);

        expect(paths).toEqual([...paths].sort());
    });

    it('keeps the entry sourcemap pointing at the original lines', () => {
        const result = createPlugin().transform("const first = 1;\nconst marker = 'entry';\n", 'plugin');
        const lines = result!.code.split('\n');
        const generatedLine = lines.findIndex((line) => line.includes('marker')) + 1;

        const original = new SourceMapConsumer(result!.map).originalPositionFor({ line: generatedLine, column: 0 });

        expect(original.line).toBe(2);
    });
});
