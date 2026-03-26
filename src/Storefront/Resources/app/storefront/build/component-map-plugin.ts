import path from 'node:path';
import type { Plugin } from 'rollup';

/**
 * Extracts the npm package name from a resolved module ID (absolute file path).
 *
 * Returns the bare specifier (e.g. 'some-library' or '@scope/pkg') for any path
 * that passes through node_modules, and null for everything else.
 */
function getPackageNameFromModuleId(moduleId: string): string | null {
    const match = moduleId.match(/[\\/]node_modules[\\/]((?:@[^/\\]+[\\/][^/\\]+|[^/\\]+))/);
    if (!match?.[1]) return null;
    return match[1].replace(/\\/g, '/');
}

/**
 * Rollup/Vite plugin — vendor map emitter.
 *
 * After Rollup has built the bundle this plugin:
 *
 * 1. Rewrites relative imports that point at vendor chunks back to bare specifiers
 *    (e.g. `'../../vendor/lib-abc123.js'` → `'some-library'`) so the browser can
 *    resolve them via the import map at runtime.
 *
 * 2. Emits `.vite/vendor-map.json` — a flat specifier → chunk-path map that PHP
 *    reads at theme:compile time to store vendor resolution data in the runtime config:
 *
 *    { "debounce": "ComponentTestApp/vendor/debounce-abc123.js" }
 *
 *    This is the only piece of build output that PHP cannot derive on its own:
 *    everything else (which components exist, where entry files live) is known from
 *    TwigComponentHelper. The content-hashed chunk filename is opaque without this map.
 */
export function componentMapPlugin(): Plugin {
    return {
        name: 'component-map-plugin',

        generateBundle(_options, bundle) {
            // Step 1: map vendor chunk filename → bare package specifier.
            const chunkToSpecifier = new Map<string, string>();

            for (const chunk of Object.values(bundle)) {
                if (chunk.type !== 'chunk' || chunk.facadeModuleId !== null) {
                    continue;
                }

                for (const moduleId of chunk.moduleIds) {
                    const pkg = getPackageNameFromModuleId(moduleId);
                    if (pkg) {
                        chunkToSpecifier.set(chunk.fileName, pkg);
                        break;
                    }
                }
            }

            if (chunkToSpecifier.size === 0) {
                return;
            }

            // Step 2: rewrite relative vendor imports to bare specifiers in entry chunks,
            // and collect the specifier → chunk-path mapping.
            const vendorMap: Record<string, string> = {};

            for (const chunk of Object.values(bundle)) {
                if (chunk.type !== 'chunk' || chunk.facadeModuleId === null) {
                    continue;
                }

                for (const importedFileName of chunk.imports) {
                    const specifier = chunkToSpecifier.get(importedFileName);
                    if (!specifier) continue;

                    const entryDir = path.posix.dirname(chunk.fileName);
                    const rel = path.posix.relative(entryDir, importedFileName);
                    const relativeImport = rel.startsWith('.') ? rel : `./${rel}`;

                    // String.split().join() instead of replaceAll() for ES2020 compatibility.
                    chunk.code = chunk.code
                        .split(`"${relativeImport}"`)
                        .join(`"${specifier}"`)
                        .split(`'${relativeImport}'`)
                        .join(`'${specifier}'`);

                    vendorMap[specifier] = importedFileName;
                }
            }

            // Step 3: emit vendor-map.json — the only build artefact PHP needs.
            this.emitFile({
                type: 'asset',
                fileName: '.vite/vendor-map.json',
                source: JSON.stringify(vendorMap, null, 2),
            });
        },
    };
}
