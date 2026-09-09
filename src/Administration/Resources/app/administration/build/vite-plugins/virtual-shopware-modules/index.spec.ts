/**
 * @sw-package framework
 */

import path from 'node:path';
import VirtualShopwareModulesPlugin, { exportNames, generateModuleSource, readRegistry } from './index';
import { parseSpecifier } from './definitions';

const administrationRoot = path.resolve(__dirname, '../../..');
const registry = readRegistry(administrationRoot);

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
        expect(VirtualShopwareModulesPlugin({ administrationRoot }).name).toBe('shopware-virtual-modules');
    });

    describe('resolveId', () => {
        it.each([
            'shopware:utils',
            'shopware:data',
            'shopware:utils/debug',
            'shopware:data/Criteria',
            'shopware:mixins/sw-form-field',
            'shopware:stores/notification',
        ])('claims %s', (specifier) => {
            expect(createPlugin().resolveId(specifier)).toBe(`\0${specifier}`);
        });

        it('refuses a key the registry does not list', () => {
            const plugin = createPlugin();

            expect(plugin.resolveId('shopware:utils/notAUtil')).toBeNull();
            expect(plugin.resolveId('shopware:stores/notARegisteredStore')).toBeNull();
            expect(plugin.resolveId('shopware:mixins/notAMixin')).toBeNull();
        });

        it('refuses a bare import of the registry-backed families', () => {
            const plugin = createPlugin();

            expect(plugin.resolveId('shopware:mixins')).toBeNull();
            expect(plugin.resolveId('shopware:stores')).toBeNull();
        });

        it('leaves every other import alone', () => {
            const plugin = createPlugin();

            expect(plugin.resolveId('src/core/service/util.service')).toBeNull();
            expect(plugin.resolveId('vue')).toBeNull();
        });
    });

    describe('load', () => {
        it('serves a specifier under its resolved id and watches the registry', () => {
            const { source, watched } = load(createPlugin(), '\0shopware:utils/debug');

            expect(source).toBe(generateModuleSource('shopware:utils/debug', registry));
            expect(watched).toEqual([path.join(administrationRoot, 'shopware-modules.json')]);
        });

        it('leaves ids it did not resolve alone', () => {
            const plugin = createPlugin();

            expect(load(plugin, 'src/core/shopware.ts').source).toBeNull();
            expect(load(plugin, '\0other-plugin:thing').source).toBeNull();
        });
    });

    describe('generateModuleSource', () => {
        it('gives a barrel one binding per member plus the branch as default', () => {
            const source = generateModuleSource('shopware:utils', registry) as string;

            registry['shopware:utils'].exports.forEach((member) => {
                expect(source).toContain(`export const ${member} = shopware.Utils["${member}"];`);
            });

            expect(source).toContain('export default shopware.Utils;');
        });

        it('gives a namespace subpath its own members plus the namespace as default', () => {
            const source = generateModuleSource('shopware:utils/debug', registry) as string;

            expect(source).toContain('export const warn = shopware.Utils["debug"]["warn"];');
            expect(source).toContain('export const error = shopware.Utils["debug"]["error"];');
            expect(source).toContain('export default shopware.Utils["debug"];');
        });

        it('gives a mixin subpath a single pure lookup, so an unused import resolves nothing', () => {
            const source = generateModuleSource('shopware:mixins/cms-element', registry) as string;

            expect(source).toContain('export default /*@__PURE__*/ shopware.Mixin.getByName("cms-element");');
            expect(source.match(/getByName/g)).toHaveLength(1);
        });

        it('gives a store subpath a composable, so the lookup happens per call', () => {
            const source = generateModuleSource('shopware:stores/notification', registry) as string;

            expect(source).toContain('export default () => shopware.Store.get("notification");');
        });

        it('fails loudly when the global Shopware object does not exist yet', () => {
            const source = generateModuleSource('shopware:data/Criteria', registry) as string;

            expect(source).toContain('const shopware = globalThis.Shopware;');
            expect(source).toContain('was imported before the global Shopware object existed');
        });

        it('returns nothing for a specifier the registry does not list', () => {
            expect(generateModuleSource('shopware:nope', registry)).toBeUndefined();
            expect(generateModuleSource('shopware:mixins/notAMixin', registry)).toBeUndefined();
        });
    });

    describe('exportNames', () => {
        it('separates a missing key from a key with no named exports', () => {
            const mixin = parseSpecifier('shopware:mixins/sw-form-field');
            const missing = parseSpecifier('shopware:mixins/notAMixin');

            expect(exportNames(registry, mixin!)).toEqual([]);
            expect(exportNames(registry, missing!)).toBeUndefined();
        });
    });
});
