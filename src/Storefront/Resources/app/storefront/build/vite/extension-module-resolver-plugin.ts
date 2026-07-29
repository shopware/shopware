import path from 'node:path';
import fs from 'node:fs';
import { createRequire } from 'node:module';
import type { Plugin } from 'vite';

type BundleEntry = {
    basePath?: string;
    storefront?: { path: string };
};

const COMPONENTS_PATH = 'Resources/views/components';
type ExtensionResolver = {
    componentRoot: string;
    nodeModulesPath: string;
    resolveFromExtension: NodeJS.RequireResolve;
};

function splitPackageId(id: string): { packageName: string; packageSubPath: string | null } {
    if (id.startsWith('@')) {
        const [scope, name, ...segments] = id.split('/');

        if (!scope || !name) {
            return { packageName: id, packageSubPath: null };
        }

        return {
            packageName: `${scope}/${name}`,
            packageSubPath: segments.length > 0 ? segments.join('/') : null,
        };
    }

    const [name, ...segments] = id.split('/');

    return {
        packageName: name ?? id,
        packageSubPath: segments.length > 0 ? segments.join('/') : null,
    };
}

function firstExistingPath(paths: string[]): string | null {
    for (const candidate of paths) {
        if (fs.existsSync(candidate) && fs.statSync(candidate).isFile()) {
            return candidate;
        }
    }

    return null;
}

function resolvePackageEntryFromNodeModules(nodeModulesPath: string, id: string): string | null {
    const { packageName, packageSubPath } = splitPackageId(id);
    const packageRoot = path.join(nodeModulesPath, packageName);

    if (!fs.existsSync(packageRoot)) {
        return null;
    }

    if (packageSubPath) {
        return firstExistingPath([
            path.join(packageRoot, packageSubPath),
            path.join(packageRoot, `${packageSubPath}.js`),
            path.join(packageRoot, packageSubPath, 'index.js'),
        ]);
    }

    const packageJsonPath = path.join(packageRoot, 'package.json');
    const entryCandidates: string[] = [];

    if (fs.existsSync(packageJsonPath)) {
        try {
            const packageJson = JSON.parse(fs.readFileSync(packageJsonPath, 'utf-8')) as {
                exports?: unknown;
                module?: string;
                main?: string;
            };

            const rootExports = packageJson.exports;
            if (typeof rootExports === 'string') {
                entryCandidates.push(rootExports);
            } else if (rootExports && typeof rootExports === 'object' && !Array.isArray(rootExports)) {
                const dotExport = (rootExports as Record<string, unknown>)['.'];
                if (typeof dotExport === 'string') {
                    entryCandidates.push(dotExport);
                } else if (dotExport && typeof dotExport === 'object' && !Array.isArray(dotExport)) {
                    const exportObject = dotExport as Record<string, unknown>;
                    for (const key of ['import', 'default', 'require']) {
                        const value = exportObject[key];
                        if (typeof value === 'string') {
                            entryCandidates.push(value);
                        }
                    }
                }
            }

            if (typeof packageJson.module === 'string') {
                entryCandidates.push(packageJson.module);
            }

            if (typeof packageJson.main === 'string') {
                entryCandidates.push(packageJson.main);
            }
        } catch {
            // Ignore malformed package metadata and continue with defaults.
        }
    }

    entryCandidates.push('index.js');

    return firstExistingPath(entryCandidates.map((entry) => path.join(packageRoot, entry)));
}

/**
 * Vite plugin that resolves bare-specifier imports (e.g.
 * `import debounce from 'debounce'`) for extension component files.
 *
 * Extension components live under Resources/views/components/ while their
 * npm dependencies are installed in the sibling
 * Resources/app/storefront/node_modules/.  Node's normal upward-scan
 * resolution never visits a sibling directory, so this plugin bridges the
 * gap by reading var/plugins.json to locate each extension's node_modules
 * and checking them when a bare specifier cannot be found through the
 * standard resolver.
 *
 * Used by both the component dev server and the vitest runner.
 */
export function extensionModuleResolverPlugin(projectRoot: string): Plugin {
    const pluginsJson = path.join(projectRoot, 'var/plugins.json');

    const resolveBundleBasePath = (basePath?: string): string => path.resolve(projectRoot, basePath ?? '');

    const extensionResolvers: ExtensionResolver[] = [];
    if (fs.existsSync(pluginsJson)) {
        const bundles = Object.values(JSON.parse(fs.readFileSync(pluginsJson, 'utf-8')) as Record<string, BundleEntry>);
        for (const bundle of bundles) {
            if (!bundle.storefront?.path) {
                continue;
            }

            const componentRoot = path.join(resolveBundleBasePath(bundle.basePath), COMPONENTS_PATH);
            if (!fs.existsSync(componentRoot)) {
                continue;
            }

            const storefrontAppDir = path.join(resolveBundleBasePath(bundle.basePath), bundle.storefront.path, '..');
            extensionResolvers.push({
                componentRoot,
                nodeModulesPath: path.join(storefrontAppDir, 'node_modules'),
                resolveFromExtension: createRequire(path.join(storefrontAppDir, 'package.json')).resolve,
            });
        }
    }

    return {
        name: 'extension-module-resolver',
        enforce: 'pre',
        resolveId(id: string, importer?: string): string | null {
            if (!id || id.startsWith('.') || id.startsWith('/') || id.startsWith('\0')) {
                return null;
            }

            const normalizedImporter = importer?.startsWith('/@fs/') ? importer.slice(4) : importer;

            const applicableResolvers = normalizedImporter
                ? extensionResolvers.filter(({ componentRoot, nodeModulesPath }) => (
                    normalizedImporter === componentRoot
                    || normalizedImporter.startsWith(componentRoot + path.sep)
                    || normalizedImporter === nodeModulesPath
                    || normalizedImporter.startsWith(nodeModulesPath + path.sep)
                ))
                : extensionResolvers;

            if (applicableResolvers.length === 0) {
                return null;
            }

            for (const { resolveFromExtension, nodeModulesPath } of applicableResolvers) {
                const fallbackResolved = resolvePackageEntryFromNodeModules(nodeModulesPath, id);
                if (fallbackResolved) {
                    return fallbackResolved;
                }

                try {
                    const resolved = resolveFromExtension(id);

                    // Ignore Node built-ins (e.g. "events") and other non-file ids.
                    if (!path.isAbsolute(resolved)) {
                        continue;
                    }

                    return resolved;
                } catch {
                    // Keep trying other resolvers.
                }
            }

            return null;
        },
    };
}
