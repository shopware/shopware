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
    generateBundle: unknown;
};

const pluginOptions = {
    administrationRoot: process.cwd(),
};

function createPlugin(options: { administrationRoot: string } = pluginOptions): CallableSetupPlugin {
    return shopwareSetupPlugin(options) as unknown as CallableSetupPlugin;
}

async function createVueFile(source: string, fileName = 'component.vue') {
    const root = await fs.mkdtemp(path.join(os.tmpdir(), 'sw-setup-vite-plugin-'));
    const vueFile = path.join(root, fileName);

    await fs.writeFile(vueFile, source);

    return vueFile;
}

async function resolveAndLoadVueFile(plugin: CallableSetupPlugin, vueFile: string) {
    const context = {
        resolve: jest.fn().mockResolvedValue({ id: vueFile }),
    };
    const resolvedId = await plugin.resolveId.call(
        context,
        `./${path.basename(vueFile)}`,
        path.join(path.dirname(vueFile), 'entry.js'),
    );
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
export { type HotUpdateOptions, createPlugin, createVueFile, resolveAndLoadVueFile, spyOnTransform };
