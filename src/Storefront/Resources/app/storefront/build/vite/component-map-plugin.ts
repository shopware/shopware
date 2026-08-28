import path from 'node:path';
import fs from 'node:fs/promises';
import type { Plugin } from 'vite';

/**
 * Rollup/Vite plugin — component build metadata emitter.
 *
 * After Rollup has built the bundle this plugin:
 *
 * 1. Rewrites relative imports in *entry* chunks that point at a facade chunk
 *    for a bare specifier back to that specifier
 *    (e.g. `'../../vendor/quickview-abc123.js'` → `'@shopware-ag/dive/quickview'`)
 *    so the browser can resolve them via the import map at runtime.
 *
 * 2. Emits `.vite/build-meta.json` with both:
 *    - `manifest`: the original Vite manifest content
 *    - `vendorMap`: a flat specifier → chunk-path map
 *
 *    {
 *      "manifest": { ... },
 *      "vendorMap": { "@shopware-ag/dive/quickview": "vendor/quickview-abc123.js" }
 *    }
 *
 * Design notes
 * ------------
 *
 * Only **entry** chunks are rewritten. Non-entry chunks (shared vendor chunks
 * and the facades Rolldown emits for dynamic `import()`s) are left alone for
 * two reasons:
 *
 *   - A package may be split across many chunks (e.g. `@shopware-ag/dive` →
 *     PerspectiveCamera-*.js, parse-error-*.js, quickview-*.js, …). Each chunk
 *     has its own, chunk-local mangled exports. Collapsing an intra-package
 *     import to a bare specifier would lose the subpath distinction and route
 *     the import to whichever chunk won the `vendorMap[specifier]` slot last,
 *     producing `SyntaxError: does not provide an export named 'i'` at runtime.
 *   - Vendor-to-vendor imports are already valid relative URLs that resolve
 *     correctly against the served location (all siblings live in the same
 *     `/components/…/vendor/` directory), so there is nothing to fix.
 *
 * Entry chunks are the only place where a static bare specifier is actually
 * needed: they are the boundary where a component's source `import 'some-pkg'`
 * must be preserved for the runtime import map.
 *
 * The specifier assigned to a chunk is the *same bare specifier the user wrote*
 * (`'@shopware-ag/dive'`, `'@shopware-ag/dive/quickview'`, `'three'`, …), not
 * just the package name. We capture this during resolution by observing every
 * `resolveId` call and recording `source → resolved.id`.
 *
 * Chunks are then mapped to a specifier in two ways:
 *
 *   1. **Facade chunk** — `chunk.facadeModuleId` is the resolved id of a
 *      recorded specifier. The chunk is a dedicated entrypoint for that
 *      specifier (the primary case for dynamic-import facades and for
 *      single-module entries).
 *   2. **Shared chunk, unambiguous owner** — the chunk is not a facade
 *      (`facadeModuleId === null`) but exactly *one* of its module ids is a
 *      recorded specifier's resolved id. This is Rolldown's default
 *      behaviour for statically-imported node_modules code that is shared
 *      across multiple entries: the module is placed in a shared chunk that
 *      can unambiguously be addressed by the bare specifier that owns it.
 *
 * If two or more specifiers resolve into the same shared chunk (e.g. Rolldown
 * combined `pkg/a` and `pkg/b` into one output) neither can unambiguously
 * claim the chunk, so we leave the imports as relative URLs — the browser
 * will fetch the sibling chunk correctly without involving the import map.
 */
export function componentMapPlugin(): Plugin {
    // Records every bare specifier we observe during resolution and the
    // absolute module id it resolves to. We only read this map at
    // `generateBundle`, so a single plugin instance is required per build (it
    // is — Vite creates the plugin once per build pipeline).
    const specifierToId = new Map<string, string>();
    let latestVendorMap: Record<string, string> = {};

    return {
        name: 'component-map-plugin',

        /**
         * Observe-only hook: records `source → resolved.id` for every bare
         * specifier we see, then returns `null` to let the normal resolution
         * chain run. `skipSelf: true` prevents our own hook from recursing
         * back into itself when we call `this.resolve`.
         *
         * We ignore:
         *   - Relative / absolute paths (they are not bare specifiers).
         *   - Virtual modules (`\0…`) emitted by other plugins.
         *   - `shopware`, which is kept external and resolved via the
         *     runtime import map by ThemeCompiler directly.
         */
        async resolveId(source, importer) {
            if (
                !importer
                || source.startsWith('.')
                || source.startsWith('\0')
                || path.isAbsolute(source)
                || source === 'shopware'
            ) {
                return null;
            }

            try {
                const resolved = await this.resolve(source, importer, { skipSelf: true });
                if (resolved && !resolved.external) {
                    specifierToId.set(source, resolved.id);
                }
            } catch {
                // Resolution failed — nothing to record here. Rolldown will
                // surface the underlying error via its normal channels.
            }

            return null;
        },

        generateBundle(_options, bundle) {
            // Reverse the specifier → id map. If multiple specifiers resolve
            // to the same id (e.g. `'pkg'` and `'pkg/index'` both land on the
            // package's main file), keep the shortest one — it is the most
            // canonical specifier users would write.
            const idToSpecifier = new Map<string, string>();
            specifierToId.forEach((id, spec) => {
                const existing = idToSpecifier.get(id);
                if (!existing || spec.length < existing.length) {
                    idToSpecifier.set(id, spec);
                }
            });

            // Step 1: map each chunk to the bare specifier it represents.
            //
            // Two cases produce a specifier:
            //
            //   1. Facade chunk — `chunk.facadeModuleId` is the resolved id
            //      of a recorded specifier. Typical for dynamic-import
            //      facades and for single-module entries.
            //   2. Unambiguous shared chunk — `facadeModuleId === null` but
            //      exactly one of the chunk's module ids is a recorded
            //      specifier's resolved id. This is Rolldown's default
            //      behaviour when a statically-imported node_modules
            //      module is shared across entries.
            //
            // A chunk that combines multiple specifier-owned modules is
            // skipped entirely: no single bare specifier can unambiguously
            // address it, so the browser must reach it via its relative URL.
            const chunkToSpecifier = new Map<string, string>();
            for (const chunk of Object.values(bundle)) {
                if (chunk.type !== 'chunk') {
                    continue;
                }

                if (chunk.facadeModuleId !== null) {
                    const spec = idToSpecifier.get(chunk.facadeModuleId);
                    if (spec) {
                        chunkToSpecifier.set(chunk.fileName, spec);
                    }
                    continue;
                }

                const matchingSpecifiers = new Set<string>();
                for (const moduleId of chunk.moduleIds) {
                    const spec = idToSpecifier.get(moduleId);
                    if (spec) {
                        matchingSpecifiers.add(spec);
                    }
                }
                if (matchingSpecifiers.size === 1) {
                    matchingSpecifiers.forEach(onlySpec => {
                        chunkToSpecifier.set(chunk.fileName, onlySpec);
                    });
                }
            }

            if (chunkToSpecifier.size === 0) {
                return;
            }

            // Step 2: rewrite static relative imports in ENTRY chunks that
            // point at a specifier-owned chunk back to the bare specifier,
            // and collect the specifier → chunk-path mapping for the runtime
            // import map.
            const vendorMap: Record<string, string> = {};

            for (const chunk of Object.values(bundle)) {
                if (chunk.type !== 'chunk' || !chunk.isEntry) {
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

            latestVendorMap = vendorMap;

            // Step 3: emit combined build metadata used by PHP import-map aggregation.
            // In Vite builds, manifest.json can be materialized only at write time.
            // We emit a placeholder here and overwrite it in writeBundle().
            this.emitFile({
                type: 'asset',
                fileName: '.vite/build-meta.json',
                source: JSON.stringify({ manifest: {}, vendorMap }, null, 2),
            });
        },

        async writeBundle(options) {
            if (!options.dir) {
                return;
            }

            const manifestPath = path.resolve(options.dir, '.vite/manifest.json');
            const buildMetaPath = path.resolve(options.dir, '.vite/build-meta.json');

            let manifest: Record<string, unknown> = {};
            try {
                const manifestRaw = await fs.readFile(manifestPath, 'utf8');
                const parsed: unknown = JSON.parse(manifestRaw);
                if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
                    manifest = parsed as Record<string, unknown>;
                }
            } catch {
                manifest = {};
            }

            await fs.writeFile(
                buildMetaPath,
                JSON.stringify({ manifest, vendorMap: latestVendorMap }, null, 2),
                'utf8',
            );
        },
    };
}
