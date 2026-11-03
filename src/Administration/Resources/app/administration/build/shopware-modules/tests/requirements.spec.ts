/**
 * @sw-package framework
 */

import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { Criteria } from 'shopware:data';
import ShopwareModulesPlugin, { type ModuleRegistry } from '../index';

const administrationRoot = path.resolve(__dirname, '../../..');

type PluginHooks = {
    resolveId: (id: string) => string | null;
    load: (this: { addWatchFile: () => void }, id: string) => string | null;
};

function createPlugin(consumer: 'host' | 'extension', root = administrationRoot): PluginHooks {
    return ShopwareModulesPlugin({ administrationRoot: root, consumer }) as unknown as PluginHooks;
}

function load(plugin: PluginHooks, specifier: string): string | null {
    return plugin.load.call({ addWatchFile: () => {} }, `\0${specifier}`);
}

/*
 * The Administration publishes its public API on the global `Shopware` object when it boots.
 * Extensions are built separately and load later, so they reach that API at runtime, never through the source.
 * This module lets them write it as ordinary imports, `import { Criteria } from 'shopware:data'`,
 * and keeps the imports honest: the same objects everywhere, only what Shopware promised, checked when building.
 */
describe('build/shopware-modules', () => {
    /**
     * @requirement Extension developers import the Administration's API like any package. The import reads the
     * `Shopware` object of the Administration the extension runs in, because an extension is built without the
     * Administration's source.
     */
    it('lets an extension import the Administration API like a package', () => {
        // ACT
        const source = load(createPlugin('extension'), 'shopware:data');

        // ASSERT
        expect(source).toContain('const shopware = globalThis.Shopware;');
        expect(source).toContain('export const Criteria = shopware.Data["Criteria"];');
    });

    /**
     * @requirement The Administration's own code may use the same imports. There they come straight from the
     * instance it builds, so they work from the first line that runs.
     */
    it('gives the Administration itself the same imports, from its own instance', () => {
        // ACT
        const source = load(createPlugin('host'), 'shopware:data');

        // ASSERT
        expect(source).toContain("import { ShopwareInstance as shopware } from 'src/core/shopware';");
        expect(source).toContain('export const Criteria = shopware.Data["Criteria"];');
    });

    /**
     * @requirement A test sees the same objects the running Administration uses, so spies and mocks on the global
     * `Shopware` reach the code under test.
     */
    it('gives tests the very objects of the running Administration', () => {
        // ACT
        const imported = Criteria;

        // ASSERT
        expect(imported).toBe(Shopware.Data.Criteria);
    });

    /**
     * @requirement Shopware decides what extensions may import, in a checked-in list. Adding to it is a visible
     * change in a pull request, and nothing outside it resolves.
     */
    it('offers exactly what the checked-in list publishes', () => {
        // ARRANGE
        const root = fs.mkdtempSync(path.join(os.tmpdir(), 'shopware-modules-'));
        const registry: ModuleRegistry = { 'shopware:utils': { exports: ['createId'], subpaths: { createId: [] } } };
        fs.writeFileSync(path.join(root, 'shopware-modules.json'), JSON.stringify(registry));

        try {
            // ACT
            const plugin = createPlugin('host', root);

            // ASSERT
            expect(plugin.resolveId('shopware:utils/createId')).toBe('\0shopware:utils/createId');
            expect(plugin.resolveId('shopware:utils/debug')).toBeNull();
        } finally {
            fs.rmSync(root, { recursive: true, force: true });
        }
    });

    /**
     * @requirement A wrong import fails the extension's build on the developer's machine, instead of becoming
     * `undefined` in a merchant's shop.
     */
    it('turns a wrong import into a build error', () => {
        // ACT
        const resolved = createPlugin('extension').resolveId('shopware:utils/notAUtil');

        // ASSERT
        expect(resolved).toBeNull();
    });

    /**
     * @requirement An extension built for a newer Shopware can still be installed on an older one. Then the error
     * names the Shopware version the extension needs, instead of a bare TypeError.
     */
    it('explains which Shopware version an extension needs', () => {
        // ACT
        const source = load(createPlugin('extension'), 'shopware:composables');

        // ASSERT
        expect(source).toContain('requires Shopware 6.7.16.0 or later');
        expect(source).toContain('Require shopware/administration >=6.7.16.0');
    });
});
