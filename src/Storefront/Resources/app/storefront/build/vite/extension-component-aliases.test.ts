// @vitest-environment node
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { extensionComponentAliasesPlugin, loadExtensionComponentAliases } from './extension-component-aliases';

describe('loadExtensionComponentAliases', () => {
    let projectRoot: string | undefined;

    afterEach(() => {
        vi.restoreAllMocks();
        if (projectRoot) {
            fs.rmSync(projectRoot, { recursive: true, force: true });
            projectRoot = undefined;
        }
    });

    it('loads aliases from active bundle Vite configs', async () => {
        projectRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'storefront-vite-aliases-'));
        const bundleRoot = path.join(projectRoot, 'custom/plugins/Example');
        const appDir = path.join(bundleRoot, 'Resources/app/storefront');
        fs.mkdirSync(path.join(bundleRoot, 'Resources/views/components'), { recursive: true });
        fs.mkdirSync(path.join(projectRoot, 'var'), { recursive: true });
        fs.mkdirSync(appDir, { recursive: true });
        fs.writeFileSync(path.join(projectRoot, 'var/plugins.json'), JSON.stringify({
            Storefront: { basePath: 'src/Storefront' },
            Example: { basePath: 'custom/plugins/Example' },
        }));
        fs.mkdirSync(path.join(projectRoot, 'src/Storefront/Resources/app/storefront'), { recursive: true });
        fs.mkdirSync(path.join(projectRoot, 'src/Storefront/Resources/views/components'), { recursive: true });
        fs.writeFileSync(
            path.join(projectRoot, 'src/Storefront/Resources/app/storefront/vite.components.config.mts'),
            'invalid config that must not be loaded',
        );
        fs.writeFileSync(path.join(appDir, 'vite.components.config.mts'), `
            export default {
                resolve: {
                    alias: { '@modules': '/example/src/modules' },
                },
                plugins: [],
                build: { outDir: '/ignored/build/output' },
            };
        `);

        await expect(loadExtensionComponentAliases(projectRoot)).resolves.toEqual([
            {
                bundleRoot,
                aliases: [{ find: '@modules', replacement: '/example/src/modules' }],
            },
        ]);
    });

    it('resolves the same alias against the importing bundle only', async () => {
        projectRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'storefront-vite-aliases-'));
        const bundles = ['FirstPlugin', 'SecondPlugin'];
        const modulesRoots: string[] = [];
        const componentsRoots = bundles.map((bundleName, index) => {
            const bundleRoot = path.join(projectRoot, 'custom/plugins', bundleName);
            const storefrontAppDir = path.join(bundleRoot, 'Resources/app/storefront');
            const componentRoot = path.join(bundleRoot, 'Resources/views/components');
            const aliasPath = path.join(storefrontAppDir, `src/modules-${index + 1}`);

            fs.mkdirSync(componentRoot, { recursive: true });
            fs.mkdirSync(aliasPath, { recursive: true });
            fs.writeFileSync(path.join(aliasPath, 'foo.js'), '');
            fs.writeFileSync(path.join(aliasPath, 'bar.js'), '');
            modulesRoots.push(aliasPath);
            fs.writeFileSync(path.join(storefrontAppDir, 'vite.components.config.mts'), `
                export default {
                    resolve: { alias: { '@modules': '${aliasPath}' } },
                };
            `);

            return componentRoot;
        });

        fs.mkdirSync(path.join(projectRoot, 'var'), { recursive: true });
        fs.writeFileSync(path.join(projectRoot, 'var/plugins.json'), JSON.stringify({
            FirstPlugin: { basePath: 'custom/plugins/FirstPlugin' },
            SecondPlugin: { basePath: 'custom/plugins/SecondPlugin' },
        }));

        const extensions = await loadExtensionComponentAliases(projectRoot);
        const plugin = extensionComponentAliasesPlugin(extensions, [
            { find: '@modules', replacement: '/shopware/core/modules' },
        ]);
        const resolveId = typeof plugin.resolveId === 'object' ? plugin.resolveId.handler : plugin.resolveId;
        const resolveHook = resolveId as (
            this: { resolve: (id: string) => Promise<string> },
            source: string,
            importer: string,
        ) => Promise<string | null>;
        const context = { resolve: (id: string) => Promise.resolve(id) };

        await expect(resolveHook.call(
            context,
            '@modules/file.js',
            path.join(componentsRoots[0], 'First.js'),
        )).resolves.toBe(path.join(projectRoot, 'custom/plugins/FirstPlugin/Resources/app/storefront/src/modules-1/file.js'));
        await expect(resolveHook.call(
            context,
            '@modules/bar.js',
            path.join(modulesRoots[1], 'foo.js'),
        )).resolves.toBe(path.join(modulesRoots[1], 'bar.js'));
        await expect(resolveHook.call(
            context,
            '@modules/file.js',
            path.join(projectRoot, 'src/Storefront/Resources/views/components/Sw/Test.js'),
        )).resolves.toBe('/shopware/core/modules/file.js');
    });

    it('returns no aliases when the bundle manifest is missing', async () => {
        projectRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'storefront-vite-aliases-'));

        await expect(loadExtensionComponentAliases(projectRoot)).resolves.toEqual([]);
    });

    it('warns and continues when one bundle config cannot be loaded', async () => {
        projectRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'storefront-vite-aliases-'));
        const brokenConfigFile = path.join(
            projectRoot,
            'custom/plugins/Broken/Resources/app/storefront/vite.components.config.mts',
        );
        const validBundleRoot = path.join(projectRoot, 'custom/plugins/Valid');
        const validAppDir = path.join(validBundleRoot, 'Resources/app/storefront');
        fs.mkdirSync(path.dirname(brokenConfigFile), { recursive: true });
        fs.mkdirSync(path.join(projectRoot, 'custom/plugins/Broken/Resources/views/components'), { recursive: true });
        fs.mkdirSync(path.join(validBundleRoot, 'Resources/views/components'), { recursive: true });
        fs.mkdirSync(validAppDir, { recursive: true });
        fs.mkdirSync(path.join(projectRoot, 'var'), { recursive: true });
        fs.writeFileSync(path.join(projectRoot, 'var/plugins.json'), JSON.stringify({
            Broken: { basePath: 'custom/plugins/Broken' },
            Valid: { basePath: 'custom/plugins/Valid' },
        }));
        fs.writeFileSync(brokenConfigFile, 'export default { invalid: syntax: true };');
        fs.writeFileSync(path.join(validAppDir, 'vite.components.config.mts'), `
            export default { resolve: { alias: { '@modules': '/valid/modules' } } };
        `);
        const warn = vi.spyOn(console, 'warn').mockImplementation(() => {});

        await expect(loadExtensionComponentAliases(projectRoot)).resolves.toEqual([
            {
                bundleRoot: validBundleRoot,
                aliases: [{ find: '@modules', replacement: '/valid/modules' }],
            },
        ]);
        expect(warn).toHaveBeenCalledWith(expect.stringContaining(brokenConfigFile));
    });
});
