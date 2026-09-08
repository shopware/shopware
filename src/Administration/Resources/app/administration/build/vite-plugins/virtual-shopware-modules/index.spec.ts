/**
 * @sw-package framework
 */

import path from 'node:path';
import VirtualShopwareModulesPlugin, { generateModuleSource } from './index';
import { VIRTUAL_MODULE_SPECIFIERS } from './definitions';
import { readSourceKeys } from './source-keys';

const administrationRoot = path.resolve(__dirname, '../../..');

type PluginHooks = {
    resolveId: (id: string) => string | null;
    load: (this: { addWatchFile: (file: string) => void }, id: string) => string | null;
};

function createPlugin(): PluginHooks {
    return VirtualShopwareModulesPlugin({ administrationRoot }) as unknown as PluginHooks;
}

/** `load` is called with Rollup's plugin context; only `addWatchFile` is used. */
function load(plugin: PluginHooks, id: string): { source: string | null; watched: string[] } {
    const watched: string[] = [];
    const source = plugin.load.call({ addWatchFile: (file) => watched.push(file) }, id);

    return { source, watched };
}

describe('build/vite-plugins/virtual-shopware-modules', () => {
    it('is a plugin named shopware-virtual-modules', () => {
        const plugin = VirtualShopwareModulesPlugin({ administrationRoot });

        expect(plugin.name).toBe('shopware-virtual-modules');
    });

    describe('resolveId', () => {
        it.each(VIRTUAL_MODULE_SPECIFIERS)('claims %s', (specifier) => {
            expect(createPlugin().resolveId(specifier)).toBe(`\0${specifier}`);
        });

        it('leaves every other import alone', () => {
            const plugin = createPlugin();

            expect(plugin.resolveId('shopware:nope')).toBeNull();
            expect(plugin.resolveId('src/core/service/util.service')).toBeNull();
            expect(plugin.resolveId('vue')).toBeNull();
        });
    });

    describe('load', () => {
        it.each(VIRTUAL_MODULE_SPECIFIERS)('serves %s under its resolved id', (specifier) => {
            const { source } = load(createPlugin(), `\0${specifier}`);

            expect(source).toBe(generateModuleSource(specifier, administrationRoot));
        });

        it('leaves ids it did not resolve alone', () => {
            const plugin = createPlugin();

            expect(load(plugin, 'src/core/shopware.ts').source).toBeNull();
            expect(load(plugin, '\0other-plugin:thing').source).toBeNull();
        });

        it('watches the source its export names come from', () => {
            const { watched } = load(createPlugin(), '\0shopware:stores');

            expect(watched).toEqual([path.join(administrationRoot, 'src/global.types.ts')]);
        });
    });

    describe('generateModuleSource', () => {
        it('exports one binding per source key', () => {
            const source = generateModuleSource('shopware:utils', administrationRoot);

            readSourceKeys('shopware:utils', administrationRoot).forEach((key) => {
                expect(source).toContain(`export const ${key} = shopware.Utils[${JSON.stringify(key)}];`);
            });
        });

        it('reads mixins from the registry and stores per call', () => {
            expect(generateModuleSource('shopware:mixins', administrationRoot)).toContain(
                'export const swFormFieldMixin = /*@__PURE__*/ shopware.Mixin.getByName("sw-form-field");',
            );
            expect(generateModuleSource('shopware:stores', administrationRoot)).toContain(
                'export const useNotificationStore = () => shopware.Store.get("notification");',
            );
        });

        it('marks the mixin lookups pure so unused ones can be tree-shaken', () => {
            const source = generateModuleSource('shopware:mixins', administrationRoot);
            const bindings = source.split('\n').filter((line) => line.startsWith('export const'));

            expect(bindings.every((line) => line.includes('/*@__PURE__*/'))).toBe(true);
        });

        it('fails loudly when the global Shopware object does not exist yet', () => {
            const source = generateModuleSource('shopware:data', administrationRoot);

            expect(source).toContain('const shopware = globalThis.Shopware;');
            expect(source).toContain('was imported before the global Shopware object existed');
        });

        it('rejects a specifier it has no definition for', () => {
            expect(() => generateModuleSource('shopware:nope', administrationRoot)).toThrow(
                '"shopware:nope" is not a Shopware virtual module.',
            );
        });
    });
});
