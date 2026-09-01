/**
 * @sw-package framework
 */

import fs from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';
import { createRequire } from 'node:module';
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
type ProbePosition = { line: number; column: number };
type ProbeSide = {
    generatedIndex: number;
    authoredIndex: number;
    authoredPosition: ProbePosition;
    mappedPosition: { source: string; line: number };
};
type ProbeResult = {
    sources: string[];
    loweredSourceCount: number;
    base: ProbeSide;
    override: ProbeSide;
};
type HotUpdateModule = { id: string };
type CallableSetupPlugin = {
    name: string;
    enforce: string;
    resolveId(source: string, importer: string): Promise<string | null>;
    load(id: string): Promise<LoadedModule | null>;
    transform(code: string, id: string): Promise<LoadedModule | null>;
    hotUpdate(options: { file: string; modules: HotUpdateModule[]; type: string }): HotUpdateModule[] | undefined;
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

    // First test to run the real SFC transform: it pays the vue-setup-transform require/compile
    // warmup for the whole worker, which can exceed the default CI timeout under CPU contention.
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
        expect(loaded?.code).toContain('Shopware.Component.attachOverrides(');
        expect(loaded?.code).toContain("name: 'sw-my-component'");
        expect(loaded?.map.sources).toContain(vueFile);
        expect(loaded?.map.sources).not.toContain('sw-my-component.vue');
        expect(loaded?.map.sourcesContent).toContain(source);
        expect(loaded?.map.mappings).not.toBe('');
        // The real `.vue` is not a Rollup module of its own, so load() must register it as a watched
        // dependency for HMR/watch invalidation.
        expect(loadContext.addWatchFile).toHaveBeenCalledWith(vueFile);
    }, 30000);

    it('reuses the resolveId transform in load instead of transforming twice', async () => {
        const plugin = createPlugin();
        const source = `<script setup>
const count = 1;
swDefinePublic({ count });
</script>`;
        const vueFile = await createVueFile(source, 'sw-cached-component.vue');
        // The plugin requires the shared transform through node's module cache, so spying on the
        // cached export intercepts its calls.
        const nodeRequire = createRequire(path.join(process.cwd(), 'package.json'));
        const transformModule = nodeRequire(path.join(process.cwd(), 'build/vue-setup-transform/index.js')) as {
            transformShopwareSetupSfc: (code: string, fileName: string) => unknown;
        };
        const transformSpy = jest.spyOn(transformModule, 'transformShopwareSetupSfc');

        const { loaded } = await resolveAndLoadVueFile(plugin, vueFile);

        // One transform for resolveId + load; load only re-reads to verify the stash is current.
        expect(loaded).toHaveProperty('code');
        expect(
            transformSpy.mock.calls.filter(
                ([
                    ,
                    fileName,
                ]) => fileName === vueFile,
            ),
        ).toHaveLength(1);

        transformSpy.mockRestore();
    });

    it('serves the current file content when the file changed after resolveId stashed its transform', async () => {
        const plugin = createPlugin();
        const vueFile = await createVueFile(
            `<script setup>
const count = 1;
swDefinePublic({ count });
</script>`,
            'sw-stale-stash-component.vue',
        );
        const context = {
            resolve: jest.fn().mockResolvedValue({ id: vueFile }),
        };
        const resolvedId = await plugin.resolveId.call(
            context,
            `./${path.basename(vueFile)}`,
            path.join(path.dirname(vueFile), 'entry.js'),
        );

        // Edit between resolveId (stash of the old content) and load - the issue #19469 shape where
        // a stale stash made every edit arrive one save late in the dev server.
        await fs.writeFile(
            vueFile,
            `<script setup>
const countEdited = 2;
swDefinePublic({ countEdited });
</script>`,
        );

        const loaded = await plugin.load.call({ addWatchFile: jest.fn() }, resolvedId as string);

        expect(loaded?.code).toContain('countEdited');
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
        expect(loaded?.code).toContain("Shopware.Component.overrideComponentSetup()('sw-my-component'");
    });

    it('supports direct transform calls for toolchains that do not use Vite resolve/load', async () => {
        const plugin = createPlugin();
        const source = `<script setup>
const count = 1;
swDefinePublic({ count });
</script>`;

        const result = await plugin.transform(source, '/example/component.vue');

        expect(result).toHaveProperty('code');
        expect(result?.code).toContain('Shopware.Component.attachOverrides(');
        expect(result?.map.sources).toContain('/example/component.vue');
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

    describe('hot updates', () => {
        function createHotUpdateContext(knownVirtualIds: string[]) {
            return {
                environment: {
                    moduleGraph: {
                        getModuleById: jest.fn((id: string) => (knownVirtualIds.includes(id) ? { id } : undefined)),
                    },
                },
            };
        }

        it('maps a changed .vue file to its virtual module so the dev server invalidates it', () => {
            const plugin = createPlugin();
            const virtualId = '/example/sw-my-component.vue.shopware-setup.vue';
            const context = createHotUpdateContext([virtualId]);
            const otherModule = { id: '/example/other-module.ts' };

            // Vite keys hot updates by changed file, and the real file never becomes a module - without
            // this mapping an edit invalidated nothing (issue #19469).
            const result = plugin.hotUpdate.call(context, {
                file: '/example/sw-my-component.vue',
                modules: [otherModule],
                type: 'update',
            });

            // Appended to the modules Vite already considers affected, not substituted.
            expect(result).toEqual([
                otherModule,
                { id: virtualId },
            ]);
        });

        it('leaves a .vue file alone that was never redirected to a virtual module', () => {
            const plugin = createPlugin();
            const context = createHotUpdateContext([]);

            // A plain SFC stays a real module; @vitejs/plugin-vue handles its hot update natively.
            const result = plugin.hotUpdate.call(context, {
                file: '/example/PlainComponent.vue',
                modules: [],
                type: 'update',
            });

            expect(result).toBeUndefined();
        });

        it('does not map a virtual module id onto itself', () => {
            const plugin = createPlugin();
            const context = createHotUpdateContext([]);

            // The mapping's fixed point: the virtual id must not be mapped onto itself again.
            const result = plugin.hotUpdate.call(context, {
                file: '/example/sw-my-component.vue.shopware-setup.vue',
                modules: [],
                type: 'update',
            });

            expect(result).toBeUndefined();
            expect(context.environment.moduleGraph.getModuleById).not.toHaveBeenCalled();
        });
    });

    it('maps the written sourcemap back to the authored SFCs, for base and override alike', async () => {
        expect.hasAssertions();

        const fixtureDirectory = path.join(__dirname, 'fixtures/sourcemap-composition');
        const root = await fs.mkdtemp(path.join(os.tmpdir(), 'sw-setup-vite-map-'));

        await fs.cp(fixtureDirectory, root, { recursive: true });

        // Run the TypeScript probe through jiti's CLI rather than `node probe.ts` directly: node only
        // strips types natively from v22.6+/v23.6, but the admin supports node >= 20, so native execution
        // would break there. jiti transpiles on the fly on every supported node.
        const jitiDir = path.dirname(require.resolve('jiti/package.json'));
        const jitiPackage = JSON.parse(await fs.readFile(path.join(jitiDir, 'package.json'), 'utf8')) as {
            bin: { jiti: string };
        };
        const jitiBin = path.join(jitiDir, jitiPackage.bin.jiti);
        const { stdout } = await execFileAsync(
            process.execPath,
            [
                jitiBin,
                path.join(root, 'probe.ts'),
            ],
            {
                cwd: process.cwd(),
                env: {
                    ...process.env,
                    SHOPWARE_ADMIN_ROOT: process.cwd(),
                },
            },
        );
        const { sources, loweredSourceCount, base, override } = JSON.parse(stdout) as ProbeResult;

        // The probe reads the map file the build wrote, not the in-memory chunk: the `.js.map` is
        // serialized from the emitted asset, so asserting on the chunk object hid a bug where every
        // shipped plugin map still named the virtual `.shopware-setup.vue` files.
        expect(sources.filter((source) => source.includes('.shopware-setup.vue'))).toEqual([]);
        expect(sources.filter((source) => source.startsWith('/'))).toEqual([]);

        // Content has to be the author's code; embedding the transform output would show `__swSetupAuthor_`
        // aliases and the generated footer in the debugger.
        expect(loweredSourceCount).toBe(0);

        // A base component keeps its body in place; an override has its body relocated into a callback, so
        // only the override proves the transform's own map is composed rather than merely renamed.
        const sides: { probe: ProbeSide; file: string }[] = [
            { probe: base, file: 'src/sw-nested-component.vue' },
            { probe: override, file: 'src/sw-nested-component.override.vue' },
        ];

        sides.forEach(({ probe, file }) => {
            expect(probe.generatedIndex).toBeGreaterThanOrEqual(0);
            expect(probe.authoredIndex).toBeGreaterThanOrEqual(0);
            expect(probe.mappedPosition.source).toContain(file);
            expect(probe.mappedPosition.line).toBe(probe.authoredPosition.line);
        });
    }, 60000);

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

    it('ignores non-vue files', async () => {
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

    it('surfaces a clear error when the shared transform cannot be loaded', async () => {
        // A root inside the package but without a transform under it: `src/build/vue-setup-transform`
        // does not exist, while staying inside the package keeps the `require` itself resolvable.
        const plugin = createPlugin({ administrationRoot: path.join(process.cwd(), 'src') });
        const source = `<script setup>
const count = 1;
swDefinePublic({ count });
</script>`;

        // A bad `administrationRoot` is a config error, not a per-file one: the loader fails on the same
        // unresolvable module. Asserted through `rejects` rather than a caught value, because a rejection
        // reason is `unknown` in TypeScript and this needs no cast to read its message.
        await expect(plugin.transform(source, '/example/sw-first.vue')).rejects.toThrow(
            'build/vue-setup-transform/index.js',
        );
    });
});
