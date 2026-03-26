import path from 'node:path';
import fs from 'node:fs';
import type { Plugin } from 'vite';

type BundleEntry = {
    basePath?: string;
    components?: { path: string };
    storefront?: { path: string };
};

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
    const nodeModulesPaths: string[] = fs.existsSync(pluginsJson)
        ? Object.values(JSON.parse(fs.readFileSync(pluginsJson, 'utf-8')) as Record<string, BundleEntry>)
            .filter(b => b.components?.path && b.storefront?.path)
            .map(b => {
                const storefrontSrc = path.join(projectRoot, b.basePath ?? '', b.storefront!.path);
                return path.join(storefrontSrc, '..', 'node_modules');
            })
            .filter(p => fs.existsSync(p))
        : [];

    return {
        name: 'extension-module-resolver',
        resolveId(id: string): string | null {
            if (!id || id.startsWith('.') || id.startsWith('/') || id.startsWith('\0')) {
                return null;
            }

            // Support scoped packages (@scope/pkg) and sub-path imports (pkg/sub).
            const parts = id.split('/');
            const pkgName = id.startsWith('@') ? parts.slice(0, 2).join('/') : (parts[0] ?? id);

            for (const nodeModulesPath of nodeModulesPaths) {
                const pkgDir = path.join(nodeModulesPath, pkgName);
                if (!fs.existsSync(pkgDir)) continue;

                const pkgJsonPath = path.join(pkgDir, 'package.json');
                if (fs.existsSync(pkgJsonPath)) {
                    const pkg = JSON.parse(fs.readFileSync(pkgJsonPath, 'utf-8')) as {
                        module?: string;
                        main?: string;
                    };
                    // Prefer ESM entry (module field) over CJS (main).
                    const entry = pkg.module ?? pkg.main ?? 'index.js';
                    const resolved = path.join(pkgDir, entry);
                    if (fs.existsSync(resolved)) return resolved;
                }

                const indexJs = path.join(pkgDir, 'index.js');
                if (fs.existsSync(indexJs)) return indexJs;
            }

            return null;
        },
    };
}
