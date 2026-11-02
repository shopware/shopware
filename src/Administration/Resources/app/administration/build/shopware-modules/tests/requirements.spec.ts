/**
 * @sw-package framework
 */

import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { Criteria } from 'shopware:data';
import { createId } from 'shopware:utils';
import VirtualShopwareModulesPlugin, { type ModuleRegistry } from '../index';

const administrationRoot = path.resolve(__dirname, '../../..');

type PluginHooks = {
    resolveId: (id: string) => string | null;
    load: (this: { addWatchFile: () => void }, id: string) => string | null;
};

function createPlugin(consumer: 'host' | 'extension', root = administrationRoot): PluginHooks {
    return VirtualShopwareModulesPlugin({ administrationRoot: root, consumer }) as unknown as PluginHooks;
}

function load(plugin: PluginHooks, specifier: string): string | null {
    return plugin.load.call({ addWatchFile: () => {} }, `\0${specifier}`);
}

describe('build/shopware-modules', () => {
    /**
     * @requirement A typo in a `shopware:*` import fails the build, instead of becoming `undefined` at runtime
     * in a shop. So the plugin refuses unknown specifiers and spells out every named export.
     */
    it('fails the build of an extension that imports something the Administration does not publish', () => {
        // ARRANGE
        const plugin = createPlugin('extension');

        // ACT
        const unknown = plugin.resolveId('shopware:utils/notAUtil');
        const source = load(plugin, 'shopware:utils');

        // ASSERT
        expect(unknown).toBeNull();
        expect(source).toContain('export const createId = shopware.Utils["createId"];');
    });

    /**
     * @requirement A prebuilt extension can run on an older Administration. When it needs a newer one, its
     * developer gets the fix in the message, not a bare TypeError.
     */
    it('tells the developer of an extension which Shopware version it needs', () => {
        // ACT
        const source = load(createPlugin('extension'), 'shopware:composables');

        // ASSERT
        expect(source).toContain('requires Shopware 6.7.16.0 or later');
        expect(source).toContain('Require shopware/administration >=6.7.16.0');
    });

    /**
     * @requirement What an extension may import is a checked-in file, so a new utility, mixin or store shows up
     * as a diff in a pull request instead of silently changing what resolves.
     */
    it('resolves exactly what the checked-in registry lists', () => {
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
     * @requirement A spec of code that imports `shopware:*` tests the objects the application uses, not copies,
     * so spies and mocks on the global `Shopware` reach that code.
     */
    it('lets a spec import the shopware:* modules and get the objects of the global Shopware', () => {
        // ACT
        const imported = { Criteria, createId };

        // ASSERT
        expect(imported.Criteria).toBe(Shopware.Data.Criteria);
        expect(imported.createId).toBe(Shopware.Utils.createId);
    });
});
