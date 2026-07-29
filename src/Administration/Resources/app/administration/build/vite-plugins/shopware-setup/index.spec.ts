/**
 * @sw-package framework
 */

import path from 'node:path';
import fs from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';
import { execFile } from 'node:child_process';
import { promisify } from 'node:util';
import type { SourceMap } from 'rollup';
import shopwareSetupPlugin from './index';

/**
 * The plugin's hooks as the spec drives them: plain callables.
 *
 * Vite types every hook as an optional `ObjectHook` - a union of function and `{ handler }` - so hooks
 * are not directly callable through `Plugin`. Narrowing once here keeps the assertion out of each test.
 */
type LoadedModule = { code: string; map: SourceMap };
type CallableSetupPlugin = {
    name: string;
    enforce: string;
    resolveId(source: string, importer: string): Promise<string | null>;
    load(id: string): Promise<LoadedModule | null>;
    transform(code: string, id: string): Promise<LoadedModule | null>;
    watchChange(id: string, change: { event: 'create' | 'delete' | 'update' }): void;
    generateBundle: unknown;
};

const pluginOptions = {
    administrationRoot: process.cwd(),
};

function createPlugin(options: { administrationRoot: string } = pluginOptions): CallableSetupPlugin {
    return shopwareSetupPlugin(options) as unknown as CallableSetupPlugin;
}
const execFileAsync = promisify(execFile);

function positionForIndex(source: string, index: number) {
    const beforeIndex = source.slice(0, index);
    const lines = beforeIndex.split('\n');

    return {
        line: lines.length,
        column: lines[lines.length - 1].length,
    };
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
    const loadContext = {
        addWatchFile: jest.fn(),
    };
    const loaded = await plugin.load.call(loadContext, resolvedId);

    return {
        loaded,
        resolvedId,
        loadContext,
    };
}

describe('build/vite-plugins/shopware-setup', () => {
    it('returns a pre-load Vite plugin', () => {
        const plugin = createPlugin();

        expect(plugin.name).toBe('shopware-vite-plugin-shopware-setup');
        expect(plugin.enforce).toBe('pre');
        expect(plugin).toHaveProperty('resolveId');
        expect(plugin).toHaveProperty('load');
        expect(plugin).toHaveProperty('generateBundle');
    });

    it('returns a pre-transform Vite plugin', () => {
        const plugin = createPlugin();

        expect(plugin.name).toBe('shopware-vite-plugin-shopware-setup');
        expect(plugin.enforce).toBe('pre');
        expect(plugin).toHaveProperty('transform');
    });

    it('delegates Shopware setup Vue files to the shared transform', async () => {
        const plugin = createPlugin();
        const source = `<script setup>
const count = 1;
swDefinePublic({ count });
</script>`;
        const vueFile = await createVueFile(source, 'sw-my-component.vue');

        const { loaded, resolvedId, loadContext } = await resolveAndLoadVueFile(plugin, vueFile);

        expect(resolvedId).toBe(`${vueFile}.shopware-setup.vue`);
        expect(loaded).toHaveProperty('code');
        expect(loaded.code).toContain('Shopware.Component.attachOverrides(');
        expect(loaded.code).toContain("name: 'sw-my-component'");
        expect(loaded.map.sources).toContain(vueFile);
        expect(loaded.map.sources).not.toContain('sw-my-component.vue');
        expect(loaded.map.sourcesContent).toContain(source);
        expect(loaded.map.mappings).not.toBe('');
        // The real `.vue` is not a Rollup module of its own, so load() must register it as a watched
        // dependency for HMR/watch invalidation.
        expect(loadContext.addWatchFile).toHaveBeenCalledWith(vueFile);
    });

    it('reuses the resolveId transform in load instead of transforming twice', async () => {
        /** @type {import('vite').Plugin} */
        const plugin = ShopwareSetupPlugin(pluginOptions);
        const source = `<script setup>
const count = 1;
swDefinePublic({ count });
</script>`;
        const vueFile = await createVueFile(source, 'sw-cached-component.vue');
        const transformSpy = jest.spyOn(fs, 'readFile');

        const { loaded } = await resolveAndLoadVueFile(plugin, vueFile);

        // resolveId + load together read (and therefore transform) the source exactly once; the second
        // pass is served from the resolveId cache.
        expect(loaded).toHaveProperty('code');
        expect(transformSpy.mock.calls.filter(([file]) => file === vueFile)).toHaveLength(1);

        transformSpy.mockRestore();
    });

    it('delegates .override.vue files to the shared override transform', async () => {
        const plugin = createPlugin();
        const source = `<script setup>
const count = 1;

swDefineOverride({});
</script>`;
        const vueFile = await createVueFile(source, 'sw-my-component.override.vue');

        const { loaded } = await resolveAndLoadVueFile(plugin, vueFile);

        expect(loaded).toHaveProperty('code');
        expect(loaded.code).toContain("Shopware.Component.overrideComponentSetup()('sw-my-component'");
    });

    it('supports direct transform calls for toolchains that do not use Vite resolve/load', async () => {
        const plugin = createPlugin();
        const source = `<script setup>
const count = 1;
swDefinePublic({ count });
</script>`;

        const result = await plugin.transform(source, '/example/component.vue');

        expect(result).toHaveProperty('code');
        expect(result.code).toContain('Shopware.Component.attachOverrides(');
        expect(result.map.sources).toContain('/example/component.vue');
    });

    it('rejects two base components that resolve to the same name', async () => {
        const plugin = createPlugin();
        const source = `<script setup>
const count = 1;
swDefinePublic({ count });
</script>`;

        await plugin.transform(source, '/a/sw-my-component.vue');

        await expect(plugin.transform(source, '/b/sw-my-component.vue')).rejects.toThrow(
            'Duplicate native setup base component name "sw-my-component"',
        );
    });

    it('allows an override to reuse its base component name', async () => {
        const plugin = createPlugin();

        await plugin.transform(
            `<script setup>
const count = 1;
swDefinePublic({ count });
</script>`,
            '/base/sw-my-component.vue',
        );

        await expect(
            plugin.transform(
                `<script setup>
const count = 1;

swDefineOverride({});
</script>`,
                '/override/sw-my-component.override.vue',
            ),
        ).resolves.toHaveProperty('code');
    });

    it('re-transforms the same base file without a false duplicate', async () => {
        const plugin = createPlugin();
        const source = `<script setup>
const count = 1;
swDefinePublic({ count });
</script>`;

        await plugin.transform(source, '/example/sw-my-component.vue');

        await expect(plugin.transform(source, '/example/sw-my-component.vue')).resolves.toHaveProperty('code');
    });

    it('releases a component name when its file is deleted so a move does not look like a duplicate', async () => {
        const plugin = createPlugin();
        const source = `<script setup>
const count = 1;
swDefinePublic({ count });
</script>`;

        await plugin.transform(source, '/old/sw-my-component.vue');

        // Moving a file arrives as a delete of the old path plus a transform of the new one. Without the
        // release the old path would still hold the name and the move would be reported as a duplicate
        // for the rest of the dev session.
        plugin.watchChange('/old/sw-my-component.vue', { event: 'delete' });

        await expect(plugin.transform(source, '/new/sw-my-component.vue')).resolves.toHaveProperty('code');
    });

    it('keeps reporting a genuine duplicate after an unrelated file is deleted', async () => {
        const plugin = createPlugin();
        const source = `<script setup>
const count = 1;
swDefinePublic({ count });
</script>`;

        await plugin.transform(source, '/a/sw-my-component.vue');
        plugin.watchChange('/somewhere/else/sw-other-component.vue', { event: 'delete' });

        await expect(plugin.transform(source, '/b/sw-my-component.vue')).rejects.toThrow(
            'Duplicate native setup base component name "sw-my-component"',
        );
    });

    it('keeps setup source positions when the transformed SFC map is composed by plugin-vue', async () => {
        expect.hasAssertions();

        const fixtureDirectory = path.join(__dirname, 'fixtures/sourcemap-composition');
        const root = await fs.mkdtemp(path.join(os.tmpdir(), 'sw-setup-vite-map-'));
        const sourceDirectory = path.join(root, 'src');

        await fs.cp(fixtureDirectory, root, { recursive: true });

        const componentSource = await fs.readFile(path.join(sourceDirectory, 'NestedComponent.vue'), 'utf8');
        const { stdout } = await execFileAsync(process.execPath, [path.join(root, 'probe.js')], {
            cwd: process.cwd(),
            env: {
                ...process.env,
                SHOPWARE_ADMIN_ROOT: process.cwd(),
            },
        });
        const { generatedIndex, mappedPosition } = JSON.parse(stdout);
        const originalIndex = componentSource.indexOf("computed(() => 'Hello from source')");
        const originalPosition = positionForIndex(componentSource, originalIndex);

        expect(generatedIndex).toBeGreaterThanOrEqual(0);
        expect(originalIndex).toBeGreaterThanOrEqual(0);
        expect(mappedPosition.source).toContain('src/NestedComponent.vue');
        // The intermediate virtual module must be collapsed away: its `.shopware-setup.vue` id must not
        // survive into the shipped map.
        expect(mappedPosition.source).not.toContain('.shopware-setup.vue');
        expect(mappedPosition.line).toBe(originalPosition.line);
        expect(mappedPosition.column).toBe(originalPosition.column);
    }, 30000);

    it('rejects Vue files without a script setup block', async () => {
        const plugin = createPlugin();
        const vueFile = await createVueFile('<script>const count = 1;</script>');
        const context = {
            resolve: jest.fn().mockResolvedValue({ id: vueFile }),
        };

        // resolveId runs the transform to decide whether the file is a Shopware setup SFC, so the
        // rejection surfaces here rather than at load time.
        await expect(
            plugin.resolveId.call(context, `./${path.basename(vueFile)}`, path.join(path.dirname(vueFile), 'entry.js')),
        ).rejects.toThrow('A Shopware setup component needs a <script setup> block.');
    });

    it('ignores files without Shopware setup blocks', async () => {
        const plugin = createPlugin();

        await expect(plugin.resolveId('./component.ts', '/example/entry.ts')).resolves.toBeNull();
    });

    it.each([
        '/project/node_modules/some-package/src/Widget.vue',
        'C:\\project\\node_modules\\some-package\\src\\Widget.vue',
    ])('leaves a dependency Vue file alone: %s', async (dependencyFile) => {
        const plugin = createPlugin();

        // An installed package's SFC is not an extendable component and cannot be made one - the extension
        // author does not own the file. Rejecting it would fail their build with no way to fix it.
        await expect(plugin.transform('<script>export default {};</script>', dependencyFile)).resolves.toBeNull();
    });

    it('loads the shared transform once per plugin instance', async () => {
        // A root inside the package but without a transform under it: `src/build/vue-setup-transform`
        // does not exist, while staying inside the package keeps the `require` itself resolvable.
        const plugin = createPlugin({ administrationRoot: path.join(process.cwd(), 'src') });
        const source = `<script setup>
const count = 1;
swDefinePublic({ count });
</script>`;

        // A bad `administrationRoot` is a config error, not a per-file one: the loader caches its promise,
        // so both files reject with the *same* error object. Error identity is what proves the module was
        // imported once.
        const first = await plugin.transform(source, '/example/sw-first.vue').catch((error) => error);
        const second = await plugin.transform(source, '/example/sw-second.vue').catch((error) => error);

        expect(first.message).toContain('build/vue-setup-transform/index.js');
        expect(second).toBe(first);
    });
});
