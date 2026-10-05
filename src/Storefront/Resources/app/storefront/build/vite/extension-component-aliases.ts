import fs from 'node:fs';
import path from 'node:path';
import { loadConfigFromFile, type Alias } from 'vite';

type BundleDefinition = {
    basePath?: string;
};

/**
 * Loads aliases from extension component configs for the unified dev server.
 * Bundle-specific build settings and plugins are intentionally not applied.
 */
export async function loadExtensionComponentAliases(projectRoot: string): Promise<Alias[]> {
    const pluginsFile = path.join(projectRoot, 'var/plugins.json');
    if (!fs.existsSync(pluginsFile)) {
        return [];
    }

    const bundles = JSON.parse(fs.readFileSync(pluginsFile, 'utf8')) as Record<string, BundleDefinition>;
    const aliases: Alias[] = [];

    for (const [bundleName, bundle] of Object.entries(bundles)) {
        // This file is the shared dev-server config itself, so loading it as a
        // bundle config here would recurse back into this alias loader.
        if (bundleName === 'Storefront') {
            continue;
        }

        const bundleRoot = path.resolve(projectRoot, bundle.basePath ?? '');
        const componentRoot = path.join(bundleRoot, 'Resources/views/components');
        const storefrontAppDir = path.join(bundleRoot, 'Resources/app/storefront');
        const configFile = ['vite.components.config.mts', 'vite.components.config.ts']
            .map(fileName => path.join(storefrontAppDir, fileName))
            .find(fileName => fs.existsSync(fileName));
        if (!fs.existsSync(componentRoot) || !configFile) {
            continue;
        }

        const loaded = await loadConfigFromFile(
            { command: 'serve', mode: process.env.NODE_ENV ?? 'development' },
            configFile,
            storefrontAppDir,
        );
        const configuredAliases = loaded?.config.resolve?.alias as Alias[] | Record<string, string> | undefined;
        if (Array.isArray(configuredAliases)) {
            aliases.push(...configuredAliases);
        } else if (configuredAliases) {
            aliases.push(...Object.entries(configuredAliases).map(([find, replacement]) => ({ find, replacement })));
        }
    }

    return aliases;
}
