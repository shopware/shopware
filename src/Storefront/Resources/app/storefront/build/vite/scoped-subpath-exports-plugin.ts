import path from 'node:path';
import fs from 'node:fs';
import type { Plugin } from 'vite';

type ExportEntry = { import?: string; require?: string; default?: string } | string;

/**
 * Vite plugin that correctly resolves scoped-package subpath imports
 * (e.g. `@shopware-ag/dive/quickview`) via the package's `exports` field.
 *
 * Rolldown rc.12 has a bug where these imports resolve to the package's main
 * entry (`exports["."]`) instead of the declared subpath entry
 * (`exports["./quickview"]`).  Because Vite's `resolveId` hooks run before
 * rolldown's native resolver, returning the correct absolute path here
 * sidesteps the issue entirely — for any affected package, not just a
 * hard-coded list.
 *
 * Only the `import` condition is used so the resolved file is always ESM.
 *
 * TODO: remove once rolldown fixes scoped-package subpath export resolution.
 *
 * @param nodeModulesDirs  One or more absolute paths to node_modules
 * directories to search in priority order. Multiple paths are useful
 * when component sources and their dependencies live in different locations
 * (e.g. an extension's own node_modules plus the core Storefront's
 * shared node_modules).
 */
export function scopedSubpathExportsPlugin(...nodeModulesDirs: string[]): Plugin {
    return {
        name: 'scoped-subpath-exports-resolver',
        enforce: 'pre',
        resolveId(id: string): string | null {
            // Only handle scoped packages with a subpath: @scope/pkg/sub/path
            const match = id.match(/^(@[^/]+\/[^/]+)\/(.+)$/);
            if (!match) return null;

            const packageName = match[1] ?? '';
            const subpath = match[2] ?? '';

            for (const nodeModulesDir of nodeModulesDirs) {
                const pkgDir = path.join(nodeModulesDir, ...packageName.split('/'));
                const pkgJsonPath = path.join(pkgDir, 'package.json');
                if (!fs.existsSync(pkgJsonPath)) continue;

                const pkg = JSON.parse(fs.readFileSync(pkgJsonPath, 'utf-8')) as {
                    exports?: Record<string, ExportEntry>;
                };
                const exportsMap = pkg.exports;
                if (!exportsMap) continue;

                const entry = exportsMap[`./${subpath}`];
                if (!entry) continue;

                const importPath =
                    typeof entry === 'object' ? (entry.import ?? entry.default) : entry;
                if (!importPath) continue;

                const resolved = path.resolve(pkgDir, importPath);
                if (fs.existsSync(resolved)) return resolved;
            }

            return null;
        },
    };
}
