/**
 * @sw-package framework
 */

import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import VirtualShopwareModulesPlugin, { type Consumer, type ModuleRegistry } from '../../index';

const administrationRoot = path.resolve(__dirname, '../../../..');
const registry = JSON.parse(
    fs.readFileSync(path.join(administrationRoot, 'shopware-modules.json'), 'utf8'),
) as ModuleRegistry;

type PluginHooks = {
    resolveId: (id: string) => string | null;
    load: (this: { addWatchFile: (file: string) => void }, id: string) => string | null;
    handleHotUpdate: (context: {
        file: string;
        server: {
            moduleGraph: {
                idToModuleMap: Map<string, { id: string | null }>;
                invalidateModule: (module: { id: string | null }) => void;
            };
        };
    }) => Array<{ id: string | null }> | undefined;
};

function createPlugin(root = administrationRoot, consumer: Consumer = 'host'): PluginHooks {
    return VirtualShopwareModulesPlugin({ administrationRoot: root, consumer }) as unknown as PluginHooks;
}

function writeRegistry(root: string, exportName: string): string {
    const registryFile = path.join(root, 'shopware-modules.json');
    const currentRegistry: ModuleRegistry = {
        'shopware:utils': {
            exports: [exportName],
            subpaths: { [exportName]: [] },
        },
    };

    fs.writeFileSync(registryFile, JSON.stringify(currentRegistry));

    return registryFile;
}

/** `load` is called with Rollup's plugin context; only `addWatchFile` is used. */
function load(plugin: PluginHooks, id: string): { source: string | null; watched: string[] } {
    const watched: string[] = [];
    const source = plugin.load.call({ addWatchFile: (file) => watched.push(file) }, id);

    return { source, watched };
}

/** The module the plugin serves for a specifier, as a build of that consumer loads it. */
function sourceOf(specifier: string, consumer: Consumer = 'host'): string | null {
    return load(createPlugin(administrationRoot, consumer), `\0${specifier}`).source;
}

describe('build/shopware-modules: the Vite plugin', () => {
    it('is a plugin named shopware-virtual-modules', () => {
        // ACT
        const plugin = VirtualShopwareModulesPlugin({ administrationRoot, consumer: 'host' });

        // ASSERT
        expect(plugin.name).toBe('shopware-virtual-modules');
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
            // ACT
            const resolved = createPlugin().resolveId(specifier);

            // ASSERT
            expect(resolved).toBe(`\0${specifier}`);
        });

        it.each([
            'shopware:utils/notAUtil',
            'shopware:stores/notARegisteredStore',
            'shopware:mixins/notAMixin',
            'shopware:utils/constructor',
            'shopware:utils/toString',
            'shopware:utils/__proto__',
        ])('refuses %s, a key the registry does not list', (specifier) => {
            // ACT
            const resolved = createPlugin().resolveId(specifier);

            // ASSERT
            expect(resolved).toBeNull();
        });

        it.each([
            'shopware:mixins',
            'shopware:stores',
        ])('refuses the bare import %s of a registry-backed family', (specifier) => {
            // ACT
            const resolved = createPlugin().resolveId(specifier);

            // ASSERT
            expect(resolved).toBeNull();
        });

        it.each([
            'src/core/service/util.service',
            'vue',
        ])('leaves the import %s alone', (specifier) => {
            // ACT
            const resolved = createPlugin().resolveId(specifier);

            // ASSERT
            expect(resolved).toBeNull();
        });
    });

    describe('load', () => {
        it('serves a specifier under its resolved id and watches the registry', () => {
            // ACT
            const { source, watched } = load(createPlugin(), '\0shopware:utils/debug');

            // ASSERT
            expect(source).toContain('export default shopware.Utils["debug"];');
            expect(watched).toEqual([path.join(administrationRoot, 'shopware-modules.json')]);
        });

        it.each([
            'src/core/shopware.ts',
            '\0other-plugin:thing',
        ])('leaves the id %s alone, which it did not resolve', (id) => {
            // ACT
            const { source } = load(createPlugin(), id);

            // ASSERT
            expect(source).toBeNull();
        });
    });

    describe('handleHotUpdate', () => {
        it('reloads the registry and invalidates loaded virtual modules', () => {
            // ARRANGE
            const root = fs.mkdtempSync(path.join(os.tmpdir(), 'shopware-virtual-modules-'));

            try {
                const registryFile = writeRegistry(root, 'first');
                const plugin = createPlugin(root);
                const virtualModule = { id: '\0shopware:utils' };
                const otherVirtualModule = { id: '\0other-plugin:thing' };
                const unrelatedModule = { id: '/src/main.ts' };
                const invalidateModule = jest.fn();
                const idToModuleMap = new Map(
                    [
                        virtualModule,
                        otherVirtualModule,
                        unrelatedModule,
                    ].map((module) => [
                        module.id,
                        module,
                    ]),
                );

                expect(plugin.resolveId('shopware:utils')).toBe('\0shopware:utils');
                expect(load(plugin, '\0shopware:utils').source).toContain('export const first');

                writeRegistry(root, 'second');

                // ACT
                const updatedModules = plugin.handleHotUpdate({
                    file: registryFile,
                    server: {
                        moduleGraph: {
                            idToModuleMap,
                            invalidateModule,
                        },
                    },
                });

                // ASSERT
                expect(updatedModules).toEqual([virtualModule]);
                expect(invalidateModule).toHaveBeenCalledTimes(1);
                expect(invalidateModule).toHaveBeenCalledWith(virtualModule);
                expect(plugin.resolveId('shopware:utils/first')).toBeNull();
                expect(plugin.resolveId('shopware:utils/second')).toBe('\0shopware:utils/second');
                expect(load(plugin, '\0shopware:utils').source).toContain('export const second');
            } finally {
                fs.rmSync(root, { recursive: true, force: true });
            }
        });
    });

    describe('the generated module source', () => {
        it('gives a root import one binding per member plus the branch as default', () => {
            // ACT
            const source = sourceOf('shopware:utils');

            // ASSERT
            registry['shopware:utils'].exports.forEach((member) => {
                expect(source).toContain(`export const ${member} = shopware.Utils["${member}"];`);
            });
            expect(source).toContain('export default shopware.Utils;');
        });

        it('gives a namespace subpath its own members plus the namespace as default', () => {
            // ACT
            const source = sourceOf('shopware:utils/debug');

            // ASSERT
            expect(source).toContain('export const warn = shopware.Utils["debug"]["warn"];');
            expect(source).toContain('export const error = shopware.Utils["debug"]["error"];');
            expect(source).toContain('export default shopware.Utils["debug"];');
        });

        it('gives a mixin subpath a single pure lookup, so an unused import resolves nothing', () => {
            // ACT
            const source = sourceOf('shopware:mixins/sw-form-field');

            // ASSERT
            expect(source).toContain('export default /*@__PURE__*/ shopware.Mixin.getByName("sw-form-field");');
            expect(source?.match(/getByName/g)).toHaveLength(1);
        });

        it('gives a store subpath a composable, so the lookup happens per call', () => {
            // ACT
            const source = sourceOf('shopware:stores/notification');

            // ASSERT
            expect(source).toContain('export default () => shopware.Store.get("notification");');
        });

        it('imports the instance for the host, so evaluation order settles when it exists', () => {
            // ACT
            const source = sourceOf('shopware:data/Criteria', 'host');

            // ASSERT
            expect(source).toContain("import { ShopwareInstance as shopware } from 'src/core/shopware';");
            expect(source).not.toContain('globalThis.Shopware');
        });

        it('imports the mixin registry too, so the lookup cannot run before registration', () => {
            // ACT
            const source = sourceOf('shopware:mixins/sw-form-field', 'host');

            // ASSERT
            expect(source).toContain("import 'src/app/mixin';");
        });

        it('exports the composables of the instance', () => {
            // ACT
            const source = sourceOf('shopware:composables', 'host');

            // ASSERT
            expect(source).toContain('export const useListing = shopware.Composables["useListing"];');
            expect(source).toContain('export default shopware.Composables;');
        });

        it('gives a composable subpath the composable as its only export', () => {
            // ACT
            const source = sourceOf('shopware:composables/useListing', 'host');

            // ASSERT
            expect(source).toContain('export default shopware.Composables["useListing"];');
            expect(source).not.toContain('export const');
        });

        it('reads the global for an extension, which has no Administration source to import', () => {
            // ACT
            const source = sourceOf('shopware:data/Criteria', 'extension');

            // ASSERT
            expect(source).toContain('const shopware = globalThis.Shopware;');
            expect(source).not.toContain("from 'src/core/shopware'");
            expect(source).toContain('should be unreachable');
            expect(source).not.toContain('requires Shopware');
        });

        it.each([
            'shopware:composables',
            'shopware:composables/useListing',
        ])('checks for the branch before an extension reads %s, which older Administrations lack', (specifier) => {
            // ACT
            const source = sourceOf(specifier, 'extension');

            // ASSERT
            expect(source).toMatch(/if \(!shopware\.Composables\) \{[\s\S]*requires Shopware 6\.7\.16\.0[\s\S]*export/);
        });

        it('leaves the branch check out of the host, which imports its own instance', () => {
            // ACT
            const source = sourceOf('shopware:composables', 'host');

            // ASSERT
            expect(source).not.toContain('requires Shopware');
        });

        it('re-exports the index.ts of a mini-module, without a default export when it has none', () => {
            // ACT
            const source = sourceOf('shopware:framework/public-component', 'extension');

            // ASSERT
            expect(source).toContain(
                'export const definePublicComponent = shopware.Public["framework"]["public-component"]["definePublicComponent"];',
            );
            expect(source).not.toContain('export default');
            expect(source).toMatch(/if \(!shopware\.Public\?\.\["framework"\]\) \{[\s\S]*requires Shopware 6\.7\.17\.0/);
        });

        it.each([
            'shopware:nope',
            'shopware:mixins/notAMixin',
        ])('serves nothing for %s, which the registry does not list', (specifier) => {
            // ACT
            const source = sourceOf(specifier);

            // ASSERT
            expect(source).toBeNull();
        });
    });
});
