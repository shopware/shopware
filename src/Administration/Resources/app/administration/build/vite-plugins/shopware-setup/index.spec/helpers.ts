/**
 * @sw-package framework
 */

import fs from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';
import { createRequire } from 'node:module';
import type { SourceMap } from 'rollup';
import shopwareSetupPlugin from '../index';

/**
 * The plugin's hooks as the spec drives them: plain callables.
 *
 * Vite types every hook as an optional `ObjectHook` - a union of function and `{ handler }` - so hooks
 * are not directly callable through `Plugin`. Narrowing once here keeps the assertion out of each test.
 */
type LoadedModule = { code: string; map: SourceMap };
type HotUpdateModule = { id: string };
type HotUpdateOptions = {
    file: string;
    modules: HotUpdateModule[];
    type: 'create' | 'update' | 'delete';
    read: () => Promise<string>;
};
type CallableSetupPlugin = {
    name: string;
    enforce: string;
    resolveId(source: string, importer: string): Promise<string | null>;
    load(id: string): Promise<LoadedModule | null>;
    transform(code: string, id: string): Promise<LoadedModule | null>;
    hotUpdate(options: HotUpdateOptions): Promise<HotUpdateModule[] | undefined>;
    watchChange(id: string, change: { event: 'create' | 'delete' | 'update' }): void;
    configResolved(config: { command: ViteCommand }): void;
    generateBundle: unknown;
};
type ViteCommand = 'serve' | 'build';

/**
 * Creates the plugin for the administration under test.
 *
 * Pass `command` to resolve it the way Vite does for the dev server (`serve`) or `vite build`; without
 * it the plugin runs as in a toolchain that calls its hooks directly.
 */
function createPlugin({
    administrationRoot = process.cwd(),
    command,
}: { administrationRoot?: string; command?: ViteCommand } = {}): CallableSetupPlugin {
    const plugin = shopwareSetupPlugin({ administrationRoot }) as unknown as CallableSetupPlugin;

    if (command) {
        plugin.configResolved({ command });
    }

    return plugin;
}

async function createVueFile(source: string, fileName = 'component.vue') {
    const root = await fs.mkdtemp(path.join(os.tmpdir(), 'sw-setup-vite-plugin-'));
    const vueFile = path.join(root, fileName);

    await fs.writeFile(vueFile, source);

    return vueFile;
}

/**
 * Resolves `vueFile` the way an importer next to it would, with Vite's own resolution mocked to return it.
 *
 * Returns the raw `resolveId` promise, so a test can assert a rejection; use
 * {@link resolveAndLoadVueFile} when the file is expected to compile and load.
 */
function resolveVueFile(plugin: CallableSetupPlugin, vueFile: string) {
    const context = {
        resolve: jest.fn().mockResolvedValue({ id: vueFile }),
    };

    return plugin.resolveId.call(context, `./${path.basename(vueFile)}`, path.join(path.dirname(vueFile), 'entry.js'));
}

async function resolveAndLoadVueFile(plugin: CallableSetupPlugin, vueFile: string) {
    const resolvedId = await resolveVueFile(plugin, vueFile);
    expect(resolvedId).not.toBeNull();

    const loadContext = {
        addWatchFile: jest.fn(),
    };
    const loaded = await plugin.load.call(loadContext, resolvedId as string);

    return {
        loaded,
        resolvedId,
        loadContext,
    };
}

/** The plugin requires the shared transform through node's module cache, so spying on the cached export intercepts its calls. */
function spyOnTransform() {
    const nodeRequire = createRequire(path.join(process.cwd(), 'package.json'));
    const transformModule = nodeRequire(path.join(process.cwd(), 'build/vue-setup-transform/index.js')) as {
        transformShopwareSetupSfc: (code: string, fileName: string) => unknown;
    };

    return jest.spyOn(transformModule, 'transformShopwareSetupSfc');
}

/**
 * @private
 */
export { type HotUpdateOptions, createPlugin, createVueFile, resolveAndLoadVueFile, resolveVueFile, spyOnTransform };
