// @vitest-environment node
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { afterAll, beforeAll, describe, expect, it } from 'vitest';
import { extensionModuleResolverPlugin } from './extension-module-resolver-plugin';

function resolveHook<T extends (...args: never[]) => unknown>(
    hook: T | { handler: T } | undefined,
): T | undefined {
    if (typeof hook === 'function') {
        return hook;
    }

    return hook?.handler;
}

describe('extensionModuleResolverPlugin', () => {
    let fixtureRoot: string;

    beforeAll(() => {
        fixtureRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'extension-module-resolver-plugin-'));
    });

    afterAll(() => {
        fs.rmSync(fixtureRoot, { recursive: true, force: true });
    });

    it('resolves bare package imports from extension node_modules', () => {
        const extensionBase = path.join('custom/plugins/TestPlugin');
        const componentsRoot = path.join(fixtureRoot, extensionBase, 'Resources/views/components');
        const storefrontSrc = path.join(fixtureRoot, extensionBase, 'Resources/app/storefront/src');
        const packageRoot = path.join(fixtureRoot, extensionBase, 'Resources/app/storefront/node_modules/test-package');

        fs.mkdirSync(componentsRoot, { recursive: true });
        fs.mkdirSync(storefrontSrc, { recursive: true });
        fs.mkdirSync(packageRoot, { recursive: true });

        fs.mkdirSync(path.join(fixtureRoot, 'var'), { recursive: true });
        fs.writeFileSync(path.join(fixtureRoot, 'var/plugins.json'), JSON.stringify({
            TestPlugin: {
                basePath: extensionBase,
                storefront: { path: 'Resources/app/storefront/src' },
            },
        }));

        fs.writeFileSync(path.join(packageRoot, 'package.json'), JSON.stringify({ module: 'esm/index.js' }));
        fs.mkdirSync(path.join(packageRoot, 'esm'), { recursive: true });
        fs.writeFileSync(path.join(packageRoot, 'esm/index.js'), 'export default "ok";');

        const plugin = extensionModuleResolverPlugin(fixtureRoot);
        const resolveId = resolveHook(plugin.resolveId) as ((...args: unknown[]) => unknown) | undefined;

        const resolved = resolveId?.call({}, 'test-package', undefined, { isEntry: false });

        expect(resolved).toBe(path.join(packageRoot, 'esm/index.js'));
    });

    it('resolves imports when plugins.json basePath is absolute', () => {
        const extensionBase = path.join(fixtureRoot, 'custom/apps/TestApp');
        const componentsRoot = path.join(extensionBase, 'Resources/views/components');
        const storefrontSrc = path.join(extensionBase, 'Resources/app/storefront/src');
        const packageRoot = path.join(extensionBase, 'Resources/app/storefront/node_modules/test-abs-package');

        fs.mkdirSync(componentsRoot, { recursive: true });
        fs.mkdirSync(storefrontSrc, { recursive: true });
        fs.mkdirSync(packageRoot, { recursive: true });

        fs.mkdirSync(path.join(fixtureRoot, 'var'), { recursive: true });
        fs.writeFileSync(path.join(fixtureRoot, 'var/plugins.json'), JSON.stringify({
            TestApp: {
                basePath: extensionBase,
                storefront: { path: 'Resources/app/storefront/src' },
            },
        }));

        fs.writeFileSync(path.join(packageRoot, 'package.json'), JSON.stringify({ main: 'index.js' }));
        fs.writeFileSync(path.join(packageRoot, 'index.js'), 'module.exports = "ok";');

        const plugin = extensionModuleResolverPlugin(fixtureRoot);
        const resolveId = resolveHook(plugin.resolveId) as ((...args: unknown[]) => unknown) | undefined;

        const resolved = resolveId?.call({}, 'test-abs-package', undefined, { isEntry: false });

        expect(resolved).toBe(path.join(packageRoot, 'index.js'));
    });

    it('prefers exports.import over require/main entries', () => {
        const extensionBase = path.join('custom/plugins/TestPluginImportCondition');
        const componentsRoot = path.join(fixtureRoot, extensionBase, 'Resources/views/components');
        const storefrontSrc = path.join(fixtureRoot, extensionBase, 'Resources/app/storefront/src');
        const packageRoot = path.join(fixtureRoot, extensionBase, 'Resources/app/storefront/node_modules/test-import-condition');

        fs.mkdirSync(componentsRoot, { recursive: true });
        fs.mkdirSync(storefrontSrc, { recursive: true });
        fs.mkdirSync(packageRoot, { recursive: true });
        fs.mkdirSync(path.join(packageRoot, 'esm'), { recursive: true });
        fs.mkdirSync(path.join(packageRoot, 'cjs'), { recursive: true });

        fs.mkdirSync(path.join(fixtureRoot, 'var'), { recursive: true });
        fs.writeFileSync(path.join(fixtureRoot, 'var/plugins.json'), JSON.stringify({
            TestPluginImportCondition: {
                basePath: extensionBase,
                storefront: { path: 'Resources/app/storefront/src' },
            },
        }));

        fs.writeFileSync(path.join(packageRoot, 'package.json'), JSON.stringify({
            exports: {
                '.': {
                    import: './esm/index.js',
                    require: './cjs/index.cjs',
                },
            },
            main: './cjs/index.cjs',
        }));
        fs.writeFileSync(path.join(packageRoot, 'esm/index.js'), 'export default "esm";');
        fs.writeFileSync(path.join(packageRoot, 'cjs/index.cjs'), 'module.exports = "cjs";');

        const plugin = extensionModuleResolverPlugin(fixtureRoot);
        const resolveId = resolveHook(plugin.resolveId) as ((...args: unknown[]) => unknown) | undefined;

        const resolved = resolveId?.call({}, 'test-import-condition', undefined, { isEntry: false });

        expect(resolved).toBe(path.join(packageRoot, 'esm/index.js'));
    });

    it('does not resolve for non-component importers', () => {
        const extensionBase = path.join('custom/plugins/TestPluginScoped');
        const componentsRoot = path.join(fixtureRoot, extensionBase, 'Resources/views/components');
        const storefrontSrc = path.join(fixtureRoot, extensionBase, 'Resources/app/storefront/src');
        const packageRoot = path.join(fixtureRoot, extensionBase, 'Resources/app/storefront/node_modules/test-package');

        fs.mkdirSync(componentsRoot, { recursive: true });
        fs.mkdirSync(storefrontSrc, { recursive: true });
        fs.mkdirSync(packageRoot, { recursive: true });

        fs.mkdirSync(path.join(fixtureRoot, 'var'), { recursive: true });
        fs.writeFileSync(path.join(fixtureRoot, 'var/plugins.json'), JSON.stringify({
            TestPluginScoped: {
                basePath: extensionBase,
                storefront: { path: 'Resources/app/storefront/src' },
            },
        }));

        fs.writeFileSync(path.join(packageRoot, 'package.json'), JSON.stringify({ main: 'index.js' }));
        fs.writeFileSync(path.join(packageRoot, 'index.js'), 'module.exports = "ok";');

        const plugin = extensionModuleResolverPlugin(fixtureRoot);
        const resolveId = resolveHook(plugin.resolveId) as ((...args: unknown[]) => unknown) | undefined;

        const resolved = resolveId?.call({}, 'test-package', path.join(fixtureRoot, 'src/Storefront/Resources/app/storefront/src/main.js'), { isEntry: false });

        expect(resolved).toBeNull();
    });

    it('ignores relative, absolute and virtual ids', () => {
        const plugin = extensionModuleResolverPlugin(fixtureRoot);
        const resolveId = resolveHook(plugin.resolveId) as ((...args: unknown[]) => unknown) | undefined;

        expect(resolveId?.call({}, './foo', undefined, { isEntry: false })).toBeNull();
        expect(resolveId?.call({}, '/foo', undefined, { isEntry: false })).toBeNull();
        expect(resolveId?.call({}, '\0virtual:foo', undefined, { isEntry: false })).toBeNull();
    });
});
