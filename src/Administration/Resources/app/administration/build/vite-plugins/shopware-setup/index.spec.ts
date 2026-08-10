/**
 * @sw-package framework
 */

import path from 'node:path';
import shopwareSetupPlugin from './index';

/**
 * The plugin's hooks as the spec drives them: plain callables.
 *
 * Vite types every hook as an optional `ObjectHook` - a union of function and `{ handler }` - so hooks
 * are not directly callable through `Plugin`. Narrowing once here keeps the assertion out of each test.
 */
type CallableSetupPlugin = {
    name: string;
    enforce: string;
    transform(code: string, id: string): Promise<{ code: string; map: unknown } | null>;
    watchChange(id: string, change: { event: 'create' | 'delete' | 'update' }): void;
};

const pluginOptions = {
    administrationRoot: process.cwd(),
};

function createPlugin(options: { administrationRoot: string } = pluginOptions): CallableSetupPlugin {
    return shopwareSetupPlugin(options) as unknown as CallableSetupPlugin;
}

describe('build/vite-plugins/shopware-setup', () => {
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

        const result = await plugin.transform(source, '/example/sw-my-component.vue');

        expect(result).toHaveProperty('code');
        expect(result?.code).toContain('Shopware.Component.attachOverrides(');
        expect(result?.code).toContain("name: 'sw-my-component'");
        expect(result?.map).toBeNull();
    });

    it('delegates override blocks in .override.vue files', async () => {
        const plugin = createPlugin();
        const source = `<script setup>
const count = 1;

swDefineOverride({});
</script>`;

        const result = await plugin.transform(source, '/example/sw-my-component.override.vue');

        expect(result).toHaveProperty('code');
        expect(result?.code).toContain("Shopware.Component.overrideComponentSetup()('sw-my-component'");
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

    it('rejects Vue files without a script setup block', async () => {
        const plugin = createPlugin();

        await expect(plugin.transform('<script>const count = 1;</script>', '/example/sw-component.vue')).rejects.toThrow(
            'A Shopware setup component needs a <script setup> block.',
        );
    });

    it('ignores non-vue files', async () => {
        const plugin = createPlugin();

        await expect(plugin.transform('const count = 1;', '/example/component.ts')).resolves.toBeNull();
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

        await expect(plugin.transform(source, '/example/sw-first.vue')).rejects.toThrow(
            'build/vue-setup-transform/index.js',
        );
    });
});
