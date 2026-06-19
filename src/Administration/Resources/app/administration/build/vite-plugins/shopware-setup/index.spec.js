/**
 * @sw-package framework
 */

import ShopwareSetupPlugin from './index';
import fs from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';
import { execFile } from 'node:child_process';
import { promisify } from 'node:util';

const pluginOptions = {
    administrationRoot: process.cwd(),
};
const execFileAsync = promisify(execFile);

function positionForIndex(source, index) {
    const beforeIndex = source.slice(0, index);
    const lines = beforeIndex.split('\n');

    return {
        line: lines.length,
        column: lines[lines.length - 1].length,
    };
}

async function createVueFile(source, fileName = 'component.vue') {
    const root = await fs.mkdtemp(path.join(os.tmpdir(), 'sw-setup-vite-plugin-'));
    const vueFile = path.join(root, fileName);

    await fs.writeFile(vueFile, source);

    return vueFile;
}

async function resolveAndLoadVueFile(plugin, vueFile) {
    const context = {
        resolve: jest.fn().mockResolvedValue({ id: vueFile }),
    };
    const resolvedId = await plugin.resolveId.call(
        context,
        `./${path.basename(vueFile)}`,
        path.join(path.dirname(vueFile), 'entry.js'),
    );
    const loaded = await plugin.load(resolvedId);

    return {
        loaded,
        resolvedId,
    };
}

describe('build/vite-plugins/shopware-setup', () => {
    it('returns a pre-load Vite plugin', () => {
        /** @type {import('vite').Plugin} */
        const plugin = ShopwareSetupPlugin(pluginOptions);

        expect(plugin.name).toBe('shopware-vite-plugin-shopware-setup');
        expect(plugin.enforce).toBe('pre');
        expect(plugin).toHaveProperty('resolveId');
        expect(plugin).toHaveProperty('load');
        expect(plugin).toHaveProperty('generateBundle');
    });

    it('returns a pre-transform Vite plugin', () => {
        /** @type {import('vite').Plugin} */
        const plugin = ShopwareSetupPlugin(pluginOptions);

        expect(plugin.name).toBe('shopware-vite-plugin-shopware-setup');
        expect(plugin.enforce).toBe('pre');
        expect(plugin).toHaveProperty('transform');
    });

    it('delegates Shopware setup Vue files to the shared transform', async () => {
        /** @type {import('vite').Plugin} */
        const plugin = ShopwareSetupPlugin(pluginOptions);
        const source = `<script setup>
const count = 1;
swDefinePublic({ count });
</script>`;
        const vueFile = await createVueFile(source, 'sw-my-component.vue');

        const { loaded, resolvedId } = await resolveAndLoadVueFile(plugin, vueFile);

        expect(resolvedId).toBe(`${vueFile}.shopware-setup.vue`);
        expect(loaded).toHaveProperty('code');
        expect(loaded.code).toContain('Shopware.Component.createExtendableSetup(');
        expect(loaded.code).toContain("name: 'sw-my-component'");
        expect(loaded.map.sources).toContain(vueFile);
        expect(loaded.map.sources).not.toContain('sw-my-component.vue');
        expect(loaded.map.sourcesContent).toContain(source);
        expect(loaded.map.mappings).not.toBe('');
    });

    it('delegates .override.vue files to the shared override transform', async () => {
        /** @type {import('vite').Plugin} */
        const plugin = ShopwareSetupPlugin(pluginOptions);
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
        /** @type {import('vite').Plugin} */
        const plugin = ShopwareSetupPlugin(pluginOptions);
        const source = `<script setup>
const count = 1;
</script>`;

        const result = await plugin.transform(source, '/example/component.vue');

        expect(result).toHaveProperty('code');
        expect(result.code).toContain('Shopware.Component.createExtendableSetup(');
        expect(result.map.sources).toContain('/example/component.vue');
    });

    it('rejects two base components that resolve to the same name', async () => {
        /** @type {import('vite').Plugin} */
        const plugin = ShopwareSetupPlugin(pluginOptions);
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
        /** @type {import('vite').Plugin} */
        const plugin = ShopwareSetupPlugin(pluginOptions);

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
        /** @type {import('vite').Plugin} */
        const plugin = ShopwareSetupPlugin(pluginOptions);
        const source = `<script setup>
const count = 1;
swDefinePublic({ count });
</script>`;

        await plugin.transform(source, '/example/sw-my-component.vue');

        await expect(plugin.transform(source, '/example/sw-my-component.vue')).resolves.toHaveProperty('code');
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
        expect(mappedPosition.source).not.toContain('.shopware-setup.vue');
        expect(mappedPosition.line).toBe(originalPosition.line);
        expect(mappedPosition.column).toBe(originalPosition.column);
    }, 30000);

    it('ignores Vue files without Shopware setup blocks', async () => {
        /** @type {import('vite').Plugin} */
        const plugin = ShopwareSetupPlugin(pluginOptions);
        const vueFile = await createVueFile('<script>const count = 1;</script>');
        const context = {
            resolve: jest.fn().mockResolvedValue({ id: vueFile }),
        };
        const resolvedId = await plugin.resolveId.call(
            context,
            `./${path.basename(vueFile)}`,
            path.join(path.dirname(vueFile), 'entry.js'),
        );

        expect(resolvedId).toBeNull();
    });

    it('ignores files without Shopware setup blocks', async () => {
        /** @type {import('vite').Plugin} */
        const plugin = ShopwareSetupPlugin(pluginOptions);

        await expect(plugin.resolveId('./component.ts', '/example/entry.ts')).resolves.toBeNull();
    });
});
