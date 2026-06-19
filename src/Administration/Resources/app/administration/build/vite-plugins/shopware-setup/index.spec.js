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

describe('build/vite-plugins/shopware-setup', () => {
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
        const source = `<script setup sw-component="sw-my-component">
const count = 1;
</script>`;

        const result = await plugin.transform(source, '/example/component.vue');

        expect(result).toHaveProperty('code');
        expect(result.code).toContain('Shopware.Component.createExtendableSetup(');
        expect(result.code).toContain("name: 'sw-my-component'");
        expect(result.map.sources).toContain('/example/component.vue');
        expect(result.map.sources).not.toContain('component.vue');
        expect(result.map.sourcesContent).toContain(source);
        expect(result.map.mappings).not.toBe('');
    });

    it('delegates sw-override blocks in .override.vue files', async () => {
        /** @type {import('vite').Plugin} */
        const plugin = ShopwareSetupPlugin(pluginOptions);
        const source = `<script setup sw-override="sw-my-component">
const count = 1;

swDefineOverride({});
</script>`;

        const result = await plugin.transform(source, '/example/component.override.vue');

        expect(result).toHaveProperty('code');
        expect(result.code).toContain("Shopware.Component.overrideComponentSetup()('sw-my-component'");
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
        expect(mappedPosition.line).toBe(originalPosition.line);
        expect(mappedPosition.column).toBe(originalPosition.column);
    }, 30000);

    it('ignores files without Shopware setup blocks', async () => {
        /** @type {import('vite').Plugin} */
        const plugin = ShopwareSetupPlugin(pluginOptions);

        await expect(plugin.transform('<script>const count = 1;</script>', '/example/component.vue')).resolves.toBeNull();
        await expect(plugin.transform('const count = 1;', '/example/component.ts')).resolves.toBeNull();
    });
});
