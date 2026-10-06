import fs from 'node:fs';
import path from 'node:path';
import { loadConfigFromFile, type Alias, type Plugin } from 'vite';

type BundleDefinition = {
    basePath?: string;
};

export type ExtensionComponentAliases = {
    bundleRoot: string;
    componentRoot: string;
    aliases: Alias[];
};

function matchesAlias(source: string, find: Alias['find']): boolean {
    if (typeof find === 'string') {
        return source === find || source.startsWith(`${find}/`);
    }

    find.lastIndex = 0;
    const matched = find.test(source);
    find.lastIndex = 0;

    return matched;
}

function findAliasReplacement(source: string, aliases: Alias[]): string | undefined {
    for (const alias of aliases) {
        if (!matchesAlias(source, alias.find)) {
            continue;
        }

        return typeof alias.find === 'string'
            ? alias.replacement + source.slice(alias.find.length)
            : source.replace(alias.find, alias.replacement);
    }

    return undefined;
}

/**
 * Loads aliases from extension component configs for the unified dev server.
 * Bundle-specific build settings and plugins are intentionally not applied.
 */
export async function loadExtensionComponentAliases(projectRoot: string): Promise<ExtensionComponentAliases[]> {
    const pluginsFile = path.join(projectRoot, 'var/plugins.json');
    if (!fs.existsSync(pluginsFile)) {
        return [];
    }

    const bundles = JSON.parse(fs.readFileSync(pluginsFile, 'utf8')) as Record<string, BundleDefinition>;
    const extensionAliases: ExtensionComponentAliases[] = [];

    for (const [bundleName, bundle] of Object.entries(bundles)) {
        // The core storefront config file is the shared dev-server config itself,
        // so loading it as a bundle config here would recurse back.
        if (bundleName === 'Storefront') {
            continue;
        }

        const bundleRoot = path.resolve(projectRoot, bundle.basePath ?? '');
        const componentRoot = path.join(bundleRoot, 'Resources/views/components');
        const storefrontAppDir = path.join(bundleRoot, 'Resources/app/storefront');
        const configFile = path.join(storefrontAppDir, 'vite.components.config.mts');
        if (!fs.existsSync(componentRoot) || !fs.existsSync(configFile)) {
            continue;
        }

        let loaded: Awaited<ReturnType<typeof loadConfigFromFile>>;
        try {
            loaded = await loadConfigFromFile(
                { command: 'serve', mode: process.env.NODE_ENV ?? 'development' },
                configFile,
                storefrontAppDir,
                'silent',
            );
        } catch (error) {
            const message = error instanceof Error ? error.message : String(error);
            console.warn(`[extension-component-aliases] Could not load ${configFile}: ${message}`);

            continue;
        }

        const configuredAliases = loaded?.config.resolve?.alias as Alias[] | Record<string, string> | undefined;
        if (Array.isArray(configuredAliases)) {
            extensionAliases.push({ bundleRoot, componentRoot, aliases: configuredAliases });
        } else if (configuredAliases) {
            extensionAliases.push({
                bundleRoot,
                componentRoot,
                aliases: Object.entries(configuredAliases).map(([find, replacement]) => ({ find, replacement })),
            });
        }
    }

    return extensionAliases;
}

/**
 * Applies each bundle's Vite aliases only to imports originating in that
 * bundle's resource tree.
 */
export function extensionComponentAliasesPlugin(
    extensions: ExtensionComponentAliases[],
    coreAliases: Alias[] = [],
): Plugin {
    return {
        name: 'extension-component-aliases',
        enforce: 'pre',
        async resolveId(source, importer) {
            if (!source || !importer || source.startsWith('.') || source.startsWith('/') || source.startsWith('\0')) {
                return null;
            }

            const normalizedImporter = path.normalize(importer.startsWith('/@fs/') ? importer.slice(4) : importer);
            const extension = extensions.find(({ bundleRoot }) => (
                normalizedImporter === bundleRoot
                || normalizedImporter.startsWith(bundleRoot + path.sep)
            ));

            const extensionReplacement = findAliasReplacement(source, extension?.aliases ?? []);
            if (extensionReplacement !== undefined) {
                return await this.resolve(extensionReplacement, importer, { skipSelf: true }) ?? extensionReplacement;
            }

            const coreReplacement = findAliasReplacement(source, coreAliases);
            if (coreReplacement !== undefined) {
                return await this.resolve(coreReplacement, importer, { skipSelf: true }) ?? coreReplacement;
            }

            return null;
        },
    };
}
