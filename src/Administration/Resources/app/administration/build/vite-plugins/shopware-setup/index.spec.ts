/**
 * @sw-package framework
 */

import fs from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';
import { execFile } from 'node:child_process';
import { promisify } from 'node:util';
import type { SourceMap } from 'rollup';
import shopwareSetupPlugin from './index';

type LoadedModule = { code: string; map: SourceMap | null };
type ProbeSide = {
    generatedIndex: number;
    authoredIndex: number;
    authoredPosition: { line: number; column: number };
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
    resolveId(source: string, importer: string): Promise<string | null>;
    load(id: string): Promise<LoadedModule | null>;
    hotUpdate(options: { file: string; modules: HotUpdateModule[]; type: string }): HotUpdateModule[] | undefined;
    watchChange(id: string, change: { event: 'create' | 'delete' | 'update' }): void;
};

const execFileAsync = promisify(execFile);

const baseSource = `<script setup>
const count = 1;
swDefinePublic({ count });
</script>`;

const overrideSource = `<script setup>
const count = 1;

swDefineOverride({});
</script>`;

function createPlugin(): CallableSetupPlugin {
    return shopwareSetupPlugin() as unknown as CallableSetupPlugin;
}

async function writeVueFile(fileName: string, source: string): Promise<string> {
    const root = await fs.mkdtemp(path.join(os.tmpdir(), 'sw-setup-vite-plugin-'));
    const vueFile = path.join(root, fileName);

    await fs.mkdir(path.dirname(vueFile), { recursive: true });
    await fs.writeFile(vueFile, source);

    return vueFile;
}

function resolve(plugin: CallableSetupPlugin, vueFile: string): Promise<string | null> {
    const context = { resolve: jest.fn().mockResolvedValue({ id: vueFile }) };

    return plugin.resolveId.call(context, `./${path.basename(vueFile)}`, path.join(path.dirname(vueFile), 'entry.js'));
}

async function load(plugin: CallableSetupPlugin, vueFile: string) {
    const context = { addWatchFile: jest.fn() };
    const loaded = await plugin.load.call(context, `${vueFile}.shopware-setup.vue`);

    return { loaded, addWatchFile: context.addWatchFile };
}

describe('build/vite-plugins/shopware-setup', () => {
    describe('resolveId', () => {
        it('redirects a .vue import to its virtual id without reading the file', async () => {
            // Nothing exists at this path, so a read would throw.
            const vueFile = path.join(os.tmpdir(), 'sw-never-written', 'sw-missing-component.vue');

            await expect(resolve(createPlugin(), vueFile)).resolves.toBe(`${vueFile}.shopware-setup.vue`);
        });

        it.each([
            ['a non-vue import', './component.ts'],
            ['a query import', './component.vue?raw'],
        ])('ignores %s', async (_, source) => {
            await expect(createPlugin().resolveId(source, '/example/entry.ts')).resolves.toBeNull();
        });

        it.each([
            '/project/node_modules/some-package/src/Widget.vue',
            'C:\\project\\node_modules\\some-package\\src\\Widget.vue',
            '/example/sw-component.vue.shopware-setup.vue',
        ])('leaves %s alone', async (resolvedFile) => {
            await expect(resolve(createPlugin(), resolvedFile)).resolves.toBeNull();
        });
    });

    describe('load', () => {
        it('serves the transformed SFC with a map onto the authored file', async () => {
            const vueFile = await writeVueFile('sw-my-component.vue', baseSource);

            const { loaded, addWatchFile } = await load(createPlugin(), vueFile);

            expect(loaded?.code).not.toContain('swDefinePublic');
            expect(loaded?.code).toContain('sw-my-component');
            expect(loaded?.map?.sources).toEqual([vueFile]);
            expect(loaded?.map?.sourcesContent).toEqual([baseSource]);
            // The real file is no module of its own, so watch mode only sees its edits through this.
            expect(addWatchFile).toHaveBeenCalledWith(vueFile);
        });

        it('serves the current file content after an edit', async () => {
            const plugin = createPlugin();
            const vueFile = await writeVueFile('sw-edited-component.vue', baseSource);

            await load(plugin, vueFile);
            await fs.writeFile(vueFile, baseSource.replaceAll('count', 'countEdited'));

            const { loaded } = await load(plugin, vueFile);

            expect(loaded?.code).toContain('countEdited');
        });

        it('reports transform errors', async () => {
            const vueFile = await writeVueFile('sw-plain-component.vue', '<script>const count = 1;</script>');

            await expect(load(createPlugin(), vueFile)).rejects.toThrow(
                'A Shopware setup component needs a <script setup> block.',
            );
        });

        it('leaves real module ids to Vite', async () => {
            await expect(createPlugin().load.call({ addWatchFile: jest.fn() }, '/example/entry.ts')).resolves.toBeNull();
        });
    });

    describe('unique base component names', () => {
        it('rejects two base components that resolve to the same name', async () => {
            const plugin = createPlugin();

            await load(plugin, await writeVueFile('a/sw-my-component.vue', baseSource));

            await expect(load(plugin, await writeVueFile('b/sw-my-component.vue', baseSource))).rejects.toThrow(
                'Duplicate native setup base component name "sw-my-component"',
            );
        });

        it('allows an override to reuse its base component name', async () => {
            const plugin = createPlugin();

            await load(plugin, await writeVueFile('sw-my-component.vue', baseSource));

            await expect(
                load(plugin, await writeVueFile('sw-my-component.override.vue', overrideSource)),
            ).resolves.toHaveProperty('loaded.code');
        });

        it('reloads the same base file without a false duplicate', async () => {
            const plugin = createPlugin();
            const vueFile = await writeVueFile('sw-my-component.vue', baseSource);

            await load(plugin, vueFile);

            await expect(load(plugin, vueFile)).resolves.toHaveProperty('loaded.code');
        });

        it('releases a name when its file is deleted, so a move is no duplicate', async () => {
            const plugin = createPlugin();
            const oldFile = await writeVueFile('old/sw-my-component.vue', baseSource);

            await load(plugin, oldFile);
            plugin.watchChange(oldFile, { event: 'delete' });

            await expect(load(plugin, await writeVueFile('new/sw-my-component.vue', baseSource))).resolves.toHaveProperty(
                'loaded.code',
            );
        });

        it('keeps reporting a genuine duplicate after an unrelated file is deleted', async () => {
            const plugin = createPlugin();

            await load(plugin, await writeVueFile('a/sw-my-component.vue', baseSource));
            plugin.watchChange('/somewhere/else/sw-other-component.vue', { event: 'delete' });

            await expect(load(plugin, await writeVueFile('b/sw-my-component.vue', baseSource))).rejects.toThrow(
                'Duplicate native setup base component name "sw-my-component"',
            );
        });
    });

    describe('hotUpdate', () => {
        function hotUpdate(file: string, knownIds: string[], modules: HotUpdateModule[] = []) {
            const getModuleById = jest.fn((id: string) => (knownIds.includes(id) ? { id } : undefined));
            const result = createPlugin().hotUpdate.call(
                { environment: { moduleGraph: { getModuleById } } },
                { file, modules, type: 'update' },
            );

            return { result, getModuleById };
        }

        it('adds the virtual module of a changed .vue file to the affected modules', () => {
            const virtualId = '/example/sw-my-component.vue.shopware-setup.vue';
            const otherModule = { id: '/example/other-module.ts' };

            const { result } = hotUpdate('/example/sw-my-component.vue', [virtualId], [otherModule]);

            expect(result).toEqual([otherModule, { id: virtualId }]);
        });

        it('leaves a .vue file alone that was never loaded', () => {
            expect(hotUpdate('/example/sw-unloaded.vue', []).result).toBeUndefined();
        });

        it('does not map a virtual module id onto itself', () => {
            const { result, getModuleById } = hotUpdate('/example/sw-my-component.vue.shopware-setup.vue', []);

            expect(result).toBeUndefined();
            expect(getModuleById).not.toHaveBeenCalled();
        });
    });

    it('maps the written sourcemap back to the authored SFCs, for base and override alike', async () => {
        expect.hasAssertions();

        const root = await fs.mkdtemp(path.join(os.tmpdir(), 'sw-setup-vite-map-'));

        await fs.cp(path.join(__dirname, 'fixtures/sourcemap-composition'), root, { recursive: true });

        // Through jiti's CLI because node strips types natively only from v22.6, below the supported range.
        const jitiDir = path.dirname(require.resolve('jiti/package.json'));
        const jitiPackage = JSON.parse(await fs.readFile(path.join(jitiDir, 'package.json'), 'utf8')) as {
            bin: { jiti: string };
        };
        const { stdout } = await execFileAsync(
            process.execPath,
            [path.join(jitiDir, jitiPackage.bin.jiti), path.join(root, 'probe.ts')],
            { cwd: process.cwd(), env: { ...process.env, SHOPWARE_ADMIN_ROOT: process.cwd() } },
        );
        const { sources, loweredSourceCount, base, override } = JSON.parse(stdout) as ProbeResult;

        expect(sources.filter((source) => source.includes('.shopware-setup.vue'))).toEqual([]);
        expect(sources.filter((source) => path.isAbsolute(source))).toEqual([]);
        // The debugger has to show the author's code, not the transform output.
        expect(loweredSourceCount).toBe(0);

        // An override's body is relocated by the transform, so only it proves the transform's map is
        // composed rather than merely renamed.
        [
            { probe: base, file: 'src/sw-nested-component.vue' },
            { probe: override, file: 'src/sw-nested-component.override.vue' },
        ].forEach(({ probe, file }) => {
            expect(probe.generatedIndex).toBeGreaterThanOrEqual(0);
            expect(probe.authoredIndex).toBeGreaterThanOrEqual(0);
            expect(probe.mappedPosition.source).toContain(file);
            expect(probe.mappedPosition.line).toBe(probe.authoredPosition.line);
        });
    }, 60000);
});
