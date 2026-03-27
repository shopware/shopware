/**
 * Component build orchestrator (CommonJS, runs with plain Node.js).
 *
 * Reads var/plugins.json (written by `bin/console bundle:dump`) and runs a
 * parallel Vite component build for every bundle that has a
 * `Resources/views/components` directory.
 *
 * Build strategy per bundle:
 *  - If the bundle provides `Resources/app/storefront/vite.components.config.ts`,
 *    that config is used via Vite's `configFile` option.
 *  - Otherwise the config is constructed inline — no shared env vars, safe for
 *    parallel execution.
 *
 * All bundles are built concurrently via Promise.all().
 */

/* eslint-disable no-console */
'use strict';

const path = require('node:path');
const fs = require('node:fs');
const { spawn } = require('node:child_process');
const { createRequire } = require('node:module');

// ---------------------------------------------------------------------------
// Paths
// ---------------------------------------------------------------------------
const scriptDir = __dirname;
const projectRoot = process.env.PROJECT_ROOT
    ? path.resolve(process.env.PROJECT_ROOT)
    // build/ → storefront/ → app/ → Resources/ → Storefront/ → src/ → project root
    : path.resolve(scriptDir, '../../../../../..');
const pluginsJsonPath = path.join(projectRoot, 'var', 'plugins.json');
const COMPONENTS_PATH = 'Resources/views/components';

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/** Runs a shell command asynchronously, inheriting stdio. */
function spawnAsync(cmd, opts = {}) {
    return new Promise((resolve, reject) => {
        const child = spawn(cmd, { stdio: 'inherit', shell: true, ...opts });
        child.on('close', code => {
            if (code === 0) {
                resolve();
            } else {
                reject(new Error(`Command "${cmd}" exited with code ${code}`));
            }
        });
    });
}

// ---------------------------------------------------------------------------
// Rollup/Vite plugins (plain JS — inlined to avoid env-var-based config files)
// ---------------------------------------------------------------------------

/**
 * Resolves bare specifiers from an extension's own node_modules directory.
 *
 * Component sources live in Resources/views/components/ while npm deps are
 * installed into Resources/app/storefront/node_modules/.  These are sibling
 * directory branches so the standard upward node_modules crawl never finds
 * the extension's packages.  This plugin bridges the gap by using Node's
 * createRequire to resolve bare specifiers as if require() were called from
 * the storefront app directory.
 *
 * Only applied for extension bundles (non-Storefront) that have their own
 * storefront app directory.
 */
function extensionNodeModulesPlugin(storefrontAppDir) {
    const resolve = createRequire(path.join(storefrontAppDir, 'package.json'));
    return {
        name: 'extension-node-modules-resolver',
        enforce: 'pre',
        resolveId(source) {
            if (!source || source.startsWith('.') || path.isAbsolute(source)) {
                return null;
            }
            try {
                return resolve.resolve(source);
            } catch {
                return null;
            }
        },
    };
}

/**
 * Rollup/Vite plugin — vendor map emitter.
 *
 * After Rollup has built the bundle this plugin:
 *
 * 1. Rewrites relative imports that point at vendor chunks back to bare
 *    specifiers (e.g. `'../../vendor/lib-abc123.js'` → `'some-library'`) so
 *    the browser can resolve them via the import map at runtime.
 *
 * 2. Emits `.vite/vendor-map.json` — a flat specifier → chunk-path map that
 *    PHP reads at theme:compile time to store vendor resolution data in the
 *    runtime config:
 *
 *      { "debounce": "ComponentTestApp/vendor/debounce-abc123.js" }
 */
function componentMapPlugin() {
    return {
        name: 'component-map-plugin',
        generateBundle(_options, bundle) {
            // Step 1: map vendor chunk filename → bare package specifier.
            const chunkToSpecifier = new Map();
            for (const chunk of Object.values(bundle)) {
                if (chunk.type !== 'chunk' || chunk.facadeModuleId !== null) {
                    continue;
                }
                for (const moduleId of chunk.moduleIds) {
                    const match = moduleId.match(/[\\/]node_modules[\\/]((?:@[^/\\]+[\\/][^/\\]+|[^/\\]+))/);
                    if (match?.[1]) {
                        chunkToSpecifier.set(chunk.fileName, match[1].replace(/\\/g, '/'));
                        break;
                    }
                }
            }

            if (chunkToSpecifier.size === 0) {
                return;
            }

            // Step 2: rewrite relative vendor imports to bare specifiers in entry
            // chunks, and collect the specifier → chunk-path mapping.
            const vendorMap = {};
            for (const chunk of Object.values(bundle)) {
                if (chunk.type !== 'chunk' || chunk.facadeModuleId === null) {
                    continue;
                }
                for (const importedFileName of chunk.imports) {
                    const specifier = chunkToSpecifier.get(importedFileName);
                    if (!specifier) {
                        continue;
                    }
                    const entryDir = path.posix.dirname(chunk.fileName);
                    const rel = path.posix.relative(entryDir, importedFileName);
                    const relativeImport = rel.startsWith('.') ? rel : `./${rel}`;
                    // String.split().join() instead of replaceAll() for ES2020 compatibility.
                    chunk.code = chunk.code
                        .split(`"${relativeImport}"`).join(`"${specifier}"`)
                        .split(`'${relativeImport}'`).join(`'${specifier}'`);
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

// ---------------------------------------------------------------------------
// Main (async — dynamic import() for Vite's ESM-only JS API)
// ---------------------------------------------------------------------------
(async () => {
    const { build } = await import('vite');
    const { glob } = await import('tinyglobby');

    // Load plugins.json
    let plugins;
    try {
        plugins = JSON.parse(fs.readFileSync(pluginsJsonPath, 'utf-8'));
    } catch {
        console.error(`[build-components] Could not read ${pluginsJsonPath}. Run bin/console bundle:dump first.`);
        process.exit(1);
    }

    // Collect bundles that have a components directory.
    const bundlesWithComponents = Object.entries(plugins).filter(([, config]) => {
        const componentRoot = path.resolve(projectRoot, config.basePath, COMPONENTS_PATH);
        return fs.existsSync(componentRoot);
    });

    if (bundlesWithComponents.length === 0) {
        console.log('[build-components] No bundles with components found. Nothing to build.');
        process.exit(0);
    }

    // Build all bundles in parallel. Each task handles its own npm install
    // (if needed) followed by the Vite build, so installs and builds overlap
    // across bundles as well.
    await Promise.all(bundlesWithComponents.map(async ([bundleName, config]) => {
        const bundleAbsPath = path.resolve(projectRoot, config.basePath);
        const storefrontAppDir = path.join(bundleAbsPath, 'Resources', 'app', 'storefront');
        const componentRoot = path.join(bundleAbsPath, COMPONENTS_PATH);
        const outDir = path.join(storefrontAppDir, 'dist-es', 'components');
        const customConfig = path.join(storefrontAppDir, 'vite.components.config.ts');

        console.log(`\n[build-components] Building ${bundleName}…`);
        console.log(`  components : ${componentRoot}`);
        console.log(`  output     : ${outDir}`);

        // npm install if the bundle has its own package.json.
        if (fs.existsSync(path.join(storefrontAppDir, 'package.json'))) {
            console.log(`  npm install in ${storefrontAppDir}`);
            await spawnAsync('npm install --prefer-offline', { cwd: storefrontAppDir });
        }

        // Bundle with a custom Vite config — honour it via configFile.
        if (fs.existsSync(customConfig)) {
            await build({ configFile: customConfig, root: storefrontAppDir });
            return;
        }

        // Generic build: config constructed inline — no env var sharing across
        // parallel builds.
        const isExtension = bundleName !== 'Storefront';
        const namespace = bundleName;

        const files = await glob('**/*.{js,ts}', {
            cwd: componentRoot,
            ignore: ['**/*.test.{js,ts}', '**/*.stories.*'],
        });

        // For extensions the entry name carries the namespace prefix so the
        // dist-es/components/ tree can be copied flat without path rewriting.
        // E.g. Wusel/Counter.js → ComponentTestApp/Wusel/Counter.js
        const entries = Object.fromEntries(
            files.map(file => {
                const name = file.replace(/\.(js|ts)$/, '');
                const entryName = isExtension ? `${namespace}/${name}` : name;
                return [entryName, path.join(componentRoot, file)];
            }),
        );

        await build({
            configFile: false,
            root: storefrontAppDir,
            build: {
                outDir,
                emptyOutDir: true,
                manifest: true,
                sourcemap: process.env.NODE_ENV !== 'production',
                rollupOptions: {
                    input: entries,
                    preserveEntrySignatures: 'exports-only',
                    external: ['shopware'],
                    output: {
                        format: 'es',
                        entryFileNames: '[name].js',
                        chunkFileNames: isExtension
                            ? `${namespace}/vendor/[name]-[hash].js`
                            : 'vendor/[name]-[hash].js',
                    },
                },
            },
            plugins: [
                ...(isExtension ? [extensionNodeModulesPlugin(storefrontAppDir)] : []),
                componentMapPlugin(),
            ],
        });
    }));

    console.log('\n[build-components] Done.');
})();
