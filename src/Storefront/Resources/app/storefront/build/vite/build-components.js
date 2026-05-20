/**
 * Component build orchestrator (CommonJS, runs with plain Node.js).
 *
 * Reads var/plugins.json (written by `bin/console bundle:dump`) and runs a
 * parallel Vite component build for every bundle that has a
 * `Resources/views/components` directory.
 *
 * Build strategy per bundle:
 *  - If the bundle provides `Resources/app/storefront/vite.components.config.mts`,
 *    that config is used via Vite's `configFile` option.
 *  - Otherwise the config is constructed inline — no shared env vars, safe for
 *    parallel execution.
 *
 * Each build processes JS/TS component files (producing ES modules),
 * SCSS component files (producing individual CSS assets via Vite's SCSS
 * pipeline), and plain CSS component files (routed through a virtual-CSS-
 * module shim — see the inline plainCssShimPlugin below).
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
    : path.resolve(scriptDir, '../../../../../../..');
const pluginsJsonPath = path.join(projectRoot, 'var', 'plugins.json');
const COMPONENTS_PATH = 'Resources/views/components';

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/** Runs a command asynchronously, inheriting stdio. */
function spawnAsync(cmd, args = [], opts = {}) {
    return new Promise((resolve, reject) => {
        const child = spawn(cmd, args, { stdio: 'inherit', shell: false, ...opts });
        child.on('close', code => {
            if (code === 0) {
                resolve();
            } else {
                reject(new Error(`Command "${cmd} ${args.join(' ')}" exited with code ${code}`));
            }
        });
    });
}

function shouldAllowInstallScripts(env = process.env) {
    return env.ALLOW_EXTENSION_INSTALL_SCRIPTS === '1';
}

function getNpmInstallCommand(storefrontAppDir, env = process.env) {
    const hasPackageLock = fs.existsSync(path.join(storefrontAppDir, 'package-lock.json'));
    const allowScripts = shouldAllowInstallScripts(env);

    const args = hasPackageLock
        ? ['ci', '--include=dev']
        : ['install', '--prefer-offline', '--include=dev'];

    if (!allowScripts) {
        args.push('--ignore-scripts');
    }

    return {
        cmd: 'npm',
        args,
        scriptsAllowed: allowScripts,
    };
}

function shouldInstallNpmDependencies(storefrontAppDir, env = process.env) {
    if (env.FORCE_COMPONENT_DEP_INSTALL === '1') {
        return true;
    }

    return !fs.existsSync(path.join(storefrontAppDir, 'node_modules'));
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
 * Rollup/Vite plugin — component build metadata emitter.
 *
 * After Rollup has built the bundle this plugin:
 *
 * 1. Rewrites relative imports that point at vendor chunks back to bare
 *    specifiers (e.g. `'../../vendor/lib-abc123.js'` → `'some-library'`) so
 *    the browser can resolve them via the import map at runtime.
 *
 * 2. Emits `.vite/build-meta.json` with both manifest and vendor-map data:
 *
 *      {
 *        "manifest": { ... },
 *        "vendorMap": { "debounce": "ComponentTestApp/vendor/debounce-abc123.js" }
 *      }
 */
function componentMapPlugin() {
    let latestVendorMap = {};

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

            latestVendorMap = vendorMap;

            // Step 3: emit combined build metadata used by PHP import-map aggregation.
            // In Vite builds, manifest.json can be materialized only at write time.
            // Emit a placeholder here and overwrite it in writeBundle().
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

            let manifest = {};
            try {
                const manifestRaw = await fs.promises.readFile(manifestPath, 'utf8');
                const parsed = JSON.parse(manifestRaw);
                if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
                    manifest = parsed;
                }
            } catch {
                manifest = {};
            }

            await fs.promises.writeFile(
                buildMetaPath,
                JSON.stringify({ manifest, vendorMap: latestVendorMap }, null, 2),
                'utf8',
            );
        },
    };
}

// ---------------------------------------------------------------------------
// Main (async — dynamic import() for Vite's ESM-only JS API)
// ---------------------------------------------------------------------------
async function main() {
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
        const outDir = path.join(bundleAbsPath, 'Resources', 'public', 'storefront', 'components');
        const customConfigMts = path.join(storefrontAppDir, 'vite.components.config.mts');
        const customConfig = fs.existsSync(customConfigMts) ? customConfigMts : null;

        console.log(`\n[build-components] Building ${bundleName}…`);
        console.log(`  components : ${componentRoot}`);
        console.log(`  output     : ${outDir}`);

        // Always start from a clean component output directory so deleted entries
        // (and old content hashes) cannot survive across builds.
        fs.rmSync(outDir, { recursive: true, force: true });

        // Skip bundles that have a components directory but no buildable files
        // (JS, TS, SCSS, or plain CSS).  This covers the core Storefront bundle
        // whose components/ tree currently contains only Twig templates and docs.
        const jsFiles = await glob('**/*.{js,ts}', {
            cwd: componentRoot,
            ignore: ['**/node_modules/**', '**/*.test.{js,ts}', '**/*.stories.*'],
        });
        const scssFiles = await glob('**/*.scss', {
            cwd: componentRoot,
            ignore: ['**/node_modules/**', '**/*.stories.*'],
        });
        const cssFiles = await glob('**/*.css', {
            cwd: componentRoot,
            ignore: ['**/node_modules/**', '**/*.stories.*'],
        });

        if (jsFiles.length === 0 && scssFiles.length === 0 && cssFiles.length === 0) {
            console.log('  No JS/TS/SCSS/CSS components found. Skipping.');
            return;
        }

        // Guard against a component shipping both Foo.scss and Foo.css — a
        // component has exactly one style source, never two.
        const scssBasenames = new Set(scssFiles.map(f => f.replace(/\.scss$/, '')));
        const cssBasenames  = new Set(cssFiles .map(f => f.replace(/\.css$/,  '')));
        for (const name of scssBasenames) {
            if (cssBasenames.has(name)) {
                throw new Error(
                    `[build-components] ${bundleName}: component "${name}" has both a .scss `
                    + 'and a .css file. A component may only have one style source.',
                );
            }
        }

        // Install dependencies only when missing. This keeps local builds fast
        // and avoids transient "module not found" IDE diagnostics while npm ci
        // replaces node_modules. Opt-in FORCE_COMPONENT_DEP_INSTALL=1 can
        // enforce reinstall in CI or recovery scenarios.
        // Always include dev dependencies explicitly so build tooling (e.g. Vite)
        // is available even when NODE_ENV=production is set in the parent shell.
        if (fs.existsSync(path.join(storefrontAppDir, 'package.json'))) {
            if (shouldInstallNpmDependencies(storefrontAppDir)) {
                const npmInstall = getNpmInstallCommand(storefrontAppDir);
                const scriptPolicy = npmInstall.scriptsAllowed
                    ? 'lifecycle scripts enabled'
                    : 'lifecycle scripts disabled';

                console.log(`  ${npmInstall.cmd} ${npmInstall.args.join(' ')} in ${storefrontAppDir} (${scriptPolicy})`);
                await spawnAsync(npmInstall.cmd, npmInstall.args, { cwd: storefrontAppDir });
            } else {
                console.log(`  skipping npm install in ${storefrontAppDir} (node_modules already present)`);
            }
        }

        // Bundle with a custom Vite config — honour it via configFile.
        if (customConfig !== null) {
            await build({ configFile: customConfig, root: storefrontAppDir });
            return;
        }

        // Generic build: config constructed inline — no env var sharing across
        // parallel builds.
        const isExtension = bundleName !== 'Storefront';
        const namespace = bundleName;

        // Core Storefront's app/storefront directory — used as vendor fallback for extensions.
        const coreStorefrontAppDir = path.join(scriptDir, '../..');

        // SCSS load paths: extension's own vendor first, then core Storefront vendor + scss.
        const scssLoadPaths = [
            path.join(storefrontAppDir, 'vendor'),
            path.join(coreStorefrontAppDir, 'vendor'),
            path.join(coreStorefrontAppDir, 'src/scss'),
        ];

        // Build the combined entry map.
        // For extensions the entry name carries the namespace prefix so the
        // Resources/public/storefront/components/ tree can be served without path rewriting.
        // JS:   Wusel/Counter.js   → ComponentTestApp/Wusel/Counter       (key, no ext)
        // SCSS: Wusel/Dusel.scss   → ComponentTestApp/Wusel/Dusel.scss    (key keeps .scss)
        // CSS:  Wusel/Fusel.css    → ComponentTestApp/Wusel/Fusel.css     (key keeps .css;
        //                            see plain-css shim plugin below)
        //
        // The source extension is kept in style-entry keys to prevent a silent key collision
        // with a same-named JS entry (e.g. Dusel.js + Dusel.scss would both map to 'Dusel'
        // without the extension and the object spread would silently drop the JS entry).
        // The assetFileNames function below strips the extension from the output filename.
        const makeJsEntryName = (file) => {
            const name = file.replace(/\.(js|ts)$/, '');
            return isExtension ? `${namespace}/${name}` : name;
        };
        const makeScssEntryName = (file) =>
            isExtension ? `${namespace}/${file}` : file;
        const makeCssEntryName = (file) =>
            isExtension ? `${namespace}/${file}` : file;

        // Virtual-CSS-module bridge for plain .css entries.
        //
        // Rolldown+Vite only register CSS-typed assets in the manifest when the
        // CSS flows through Vite's "pure CSS chunk" pipeline. SCSS sources hit
        // that path naturally because Vite's SCSS handler turns the .scss file
        // into a CSS-typed module whose entire chunk is pure CSS. A raw .css
        // file passed directly as a Rolldown input bypasses that pipeline and
        // is emitted as a generic asset with no manifest entry — invisible to
        // the PHP ThemeCompiler import-map aggregation.
        //
        // To route plain CSS through the same pipeline, we synthesise a virtual
        // module per plain CSS entry whose id *ends in `.css`* so Vite's CSS
        // plugin recognises it as CSS and runs it through PostCSS. The
        // `load()` hook serves the real file contents from disk. Vite then
        // produces a pure-CSS chunk, the facade module id matches isCSSRequest
        // so the asset name keeps its namespace prefix (`Ns/Foo.css` →
        // `Ns/Foo-[hash].css`), and a proper manifest entry is written. The
        // virtual module itself never lands on disk.
        const plainCssShimPrefix = '\0sw-plain-css:';
        /** @type {Map<string, string>} virtual-id → absolute .css path */
        const plainCssShims = new Map();
        /** @type {Array<[string, string]>} [entryKey, virtualId] for Rolldown input */
        const plainCssEntries = [];
        for (const cssFile of cssFiles) {
            const entryKey  = makeCssEntryName(cssFile);           // e.g. Ns/Foo.css
            const virtualId = `${plainCssShimPrefix}${entryKey}`;   // ends in .css
            plainCssShims.set(virtualId, path.join(componentRoot, cssFile));
            plainCssEntries.push([entryKey, virtualId]);
        }

        const plainCssShimPlugin = {
            name: 'sw-plain-css-shim',
            resolveId(id) {
                return plainCssShims.has(id) ? id : null;
            },
            load(id) {
                const absCssPath = plainCssShims.get(id);
                if (absCssPath === undefined) return null;
                return fs.readFileSync(absCssPath, 'utf8');
            },
        };

        const entries = {
            ...Object.fromEntries(
                jsFiles.map(file => [makeJsEntryName(file), path.join(componentRoot, file)]),
            ),
            ...Object.fromEntries(
                scssFiles.map(file => [makeScssEntryName(file), path.join(componentRoot, file)]),
            ),
            ...Object.fromEntries(plainCssEntries),
        };

        await build({
            configFile: false,
            root: storefrontAppDir,
            css: {
                preprocessorOptions: {
                    scss: {
                        loadPaths: scssLoadPaths,
                    },
                },
            },
            build: {
                outDir,
                emptyOutDir: true,
                manifest: true,
                sourcemap: process.env.NODE_ENV !== 'production',
                rolldownOptions: {
                    input: entries,
                    preserveEntrySignatures: 'exports-only',
                    external: ['shopware'],
                    output: {
                        format: 'es',
                        entryFileNames: '[name]-[hash].js',
                        chunkFileNames: isExtension
                            ? `${namespace}/vendor/[name]-[hash].js`
                            : 'vendor/[name]-[hash].js',
                        // SCSS entry keys keep the .scss extension to avoid key collisions.
                        // Rolldown appends .css → 'Ns/Wusel/Dusel.scss.css'; strip .scss before
                        // composing the output path so the file stays 'Ns/Wusel/Dusel-[hash].css'.
                        assetFileNames: (info) => {
                            const firstName = info.names[0] ?? 'asset.css';
                            if (firstName.endsWith('.scss.css')) {
                                return `${firstName.replace(/\.scss\.css$/, '')}-[hash][extname]`;
                            }
                            return '[name]-[hash][extname]';
                        },
                    },
                },
            },
            plugins: [
                ...(isExtension ? [extensionNodeModulesPlugin(storefrontAppDir)] : []),
                componentMapPlugin(),
                plainCssShimPlugin,
            ],
        });
    }));

    console.log('\n[build-components] Done.');
}

if (require.main === module) {
    void main();
}

module.exports = {
    componentMapPlugin,
    extensionNodeModulesPlugin,
    getNpmInstallCommand,
    main,
    shouldInstallNpmDependencies,
    shouldAllowInstallScripts,
    spawnAsync,
};
