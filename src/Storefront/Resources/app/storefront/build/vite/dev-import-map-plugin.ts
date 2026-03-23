import path from 'node:path';
import fs from 'node:fs';
import type { Plugin, ViteDevServer } from 'vite';
import { glob } from 'tinyglobby';
import { compileAsync } from 'sass-embedded';

type BundleEntry = {
    basePath?: string;
    technicalName?: string;
    storefront?: {
        entryFilePath?: string | null;
    };
};

const COMPONENTS_PATH = 'Resources/views/components';

/** URL prefix for the on-the-fly component SCSS middleware. */
const COMP_CSS_PREFIX = '/__sw-comp-css/';

/**
 * Converts a component file path (relative to its component root) to the
 * colon-separated tag used in `data-component` attributes and the import map.
 *
 *   'Sw/Header/Navbar.ts'      → 'Sw:Header:Navbar'
 *   'Wusel/Counter.ts' (+ ns)  → 'ComponentTestApp:Wusel:Counter'
 */
function fileToTag(relPath: string, namespace: string | undefined): string {
    const withoutExt = relPath.replace(/\.(ts|js)$/, '');
    const colonPath = withoutExt.split('/').join(':');
    return namespace ? `${namespace}:${colonPath}` : colonPath;
}

/**
 * Vite plugin that manages the component dev flag file
 * (`var/cache/storefront_components.dev.json`).
 *
 * When the Vite dev server starts it writes a JSON object with:
 *
 *   imports  — a complete ES module import map that PHP injects as
 *              `<script type="importmap">`. Every bare specifier
 *              (`shopware`, `Sw:Header:Navbar`, …) points directly to the
 *              running dev server, so no URL rewriting is needed in PHP.
 *
 *   styles   — an ordered array of Vite dev-server CSS URLs produced by the
 *              sw-theme-scss plugin. PHP uses these for `<link>` tags
 *              instead of the precompiled theme CSS. Present only when
 *              var/theme-files.json exists.
 *
 *   scripts  — an ordered array of Vite dev-server JS entry URLs. PHP uses
 *              these to replace the compiled theme JS bundle with live Vite
 *              modules. The first entry is always the core storefront
 *              main.js; any plugin bundles with a storefront entryFilePath
 *              follow in plugins.json order. Present only when
 *              var/plugins.json exists.
 *
 *   themeId  — the dumped theme id from var/theme-files.json. PHP compares
 *              this against the current sales-channel theme to decide whether
 *              dev server assets should be used for that request.
 *
 * A single flag file carries both concerns so PHP only needs to check for
 * one file to know whether the Vite dev server is running.
 *
 * When the dev server stops the file is removed and the Storefront
 * transparently falls back to the production import map and compiled CSS.
 */
/**
 * @param projectRoot  Absolute path to the Shopware project root.
 * @param scssLoadPaths  Additional SCSS load paths forwarded to the on-the-fly
 *                       component SCSS compiler (vendor/, src/scss/, …).
 */
export function devImportMapPlugin(projectRoot: string, scssLoadPaths: string[] = []): Plugin {
    const flagFile = path.join(projectRoot, 'var/cache/storefront_components.dev.json');
    let viteRoot = '';

    const cleanup = (): void => {
        try {
            fs.unlinkSync(flagFile);
        } catch {
            // File already gone or never created — harmless.
        }
    };

    return {
        name: 'sw-dev-import-map',
        // Only active during `vite` (dev server), not `vite build`.
        apply: 'serve',

        configResolved(config) {
            viteRoot = config.root;
        },

        configureServer(server: ViteDevServer) {
            // Build namespace → bundle-basePath index so the SCSS middleware can
            // resolve a URL like "/__sw-comp-css/ComponentTestApp/Wusel/Dusel.scss"
            // back to the absolute SCSS file path on disk.
            const pluginsJsonPath = path.join(projectRoot, 'var/plugins.json');
            const bundles: Record<string, BundleEntry> = fs.existsSync(pluginsJsonPath)
                ? (JSON.parse(fs.readFileSync(pluginsJsonPath, 'utf-8')) as Record<string, BundleEntry>)
                : {};

            // namespace ('' for core) → absolute components-root directory
            const nsToCompRoot: Record<string, string> = {};
            for (const [bundleName, bundle] of Object.entries(bundles)) {
                const ns = bundleName === 'Storefront' ? '' : bundleName;
                nsToCompRoot[ns] = path.join(projectRoot, bundle.basePath ?? '', COMPONENTS_PATH);
            }

            // Component style files (.scss and .css) are served through the
            // /__sw-comp-css/ middleware, not through Vite's module graph, so
            // nothing would trigger a client reload when they change. Watch
            // every component root recursively so chokidar surfaces change/
            // add/unlink events for sibling style files; a full-reload makes
            // the browser re-request the stylesheet, at which point the
            // middleware serves the fresh content. SCSS partials imported via
            // `@use`/`@import` are picked up too as long as they live under a
            // watched component root (the normal authoring location — a
            // component is always self-contained under its namespace).
            const watchedCompRoots = Object.values(nsToCompRoot).filter(fs.existsSync);
            for (const compRoot of watchedCompRoots) {
                server.watcher.add(compRoot);
            }

            const isComponentStyleFile = (file: string): boolean => {
                if (!file.endsWith('.scss') && !file.endsWith('.css')) return false;
                return watchedCompRoots.some(root => file.startsWith(root + path.sep));
            };

            // Middleware: serve individual component style files (SCSS or plain
            // CSS) as compiled CSS so PHP can use them as <link rel="stylesheet">
            // targets in dev mode — matching the per-file CSS behaviour of the
            // production Vite build.
            server.middlewares.use(COMP_CSS_PREFIX, (req, res, next) => {
                const relUrl = req.url?.replace(/^\//, '') ?? '';

                // Determine namespace and file path from the URL.
                // URLs look like "ComponentTestApp/Wusel/Dusel.scss" (namespaced)
                // or "Sw/Header/Navbar.scss" (core, no namespace). Plain CSS
                // sources follow the same pattern with a .css suffix.
                let styleAbsPath: string | undefined;
                for (const ns of Object.keys(nsToCompRoot)) {
                    const compRoot = nsToCompRoot[ns];
                    if (compRoot === undefined) continue;

                    const prefix = ns ? `${ns}/` : '';
                    if (!relUrl.startsWith(prefix)) continue;

                    const candidate = path.join(compRoot, relUrl.slice(prefix.length));
                    if (fs.existsSync(candidate)) {
                        styleAbsPath = candidate;
                        break;
                    }
                }

                if (!styleAbsPath) {
                    next();
                    return;
                }

                // Plain .css sources are served as-is; dev mode intentionally
                // skips the PostCSS pass to keep the dev server snappy, and
                // browsers understand plain CSS directly.
                if (styleAbsPath.endsWith('.css')) {
                    try {
                        const css = fs.readFileSync(styleAbsPath, 'utf8');
                        res.setHeader('Content-Type', 'text/css');
                        res.end(css);
                    } catch {
                        next();
                    }
                    return;
                }

                compileAsync(styleAbsPath, { loadPaths: scssLoadPaths, quietDeps: true })
                    .then(result => {
                        res.setHeader('Content-Type', 'text/css');
                        res.end(result.css);
                    })
                    .catch(() => next());
            });

            const write = async (): Promise<void> => {
                const port = server.config.server.port ?? 5175;
                const origin = `http://localhost:${port}`;
                const imports: Record<string, string> = {};

                // shopware runtime module — lives inside the Vite root so it
                // gets a clean URL without the /@fs/ prefix.
                const shopwareSrc = path.join(viteRoot, 'src/shopware.ts');
                if (fs.existsSync(shopwareSrc)) {
                    imports['shopware'] = `${origin}/src/shopware.ts`;
                }

                for (const [bundleName, bundle] of Object.entries(bundles)) {
                    // The core Storefront bundle uses bare component names
                    // (e.g. 'Sw:Header:Navbar'); all other bundles are prefixed
                    // with their bundle name as a namespace.
                    const namespace = bundleName === 'Storefront' ? undefined : bundleName;
                    const compRoot = path.join(projectRoot, bundle.basePath ?? '', COMPONENTS_PATH);

                    if (!fs.existsSync(compRoot)) continue;

                    const files = await glob('**/*.{js,ts}', {
                        cwd: compRoot,
                        ignore: ['**/node_modules/**', '**/*.test.{js,ts}', '**/*.stories.*'],
                    });

                    for (const file of files) {
                        const tag = fileToTag(file, namespace);
                        const absPath = path.join(compRoot, file);
                        // Component sources live outside the Vite root, so they
                        // are served via the /@fs/ prefix (Vite allows this when
                        // server.fs.allow covers the project root).
                        imports[tag] = `${origin}/@fs${absPath}`;
                    }
                }

                // CSS URLs for dev mode:
                // 1. Main theme SCSS (sw-theme-scss middleware) — when theme-files.json exists.
                // 2. Individual component SCSS files — served on-the-fly by the
                //    /__sw-comp-css/ middleware above, mirroring the per-file CSS
                //    behaviour of the production Vite build.
                const themeFilesPath = path.join(projectRoot, 'var/theme-files.json');
                let themeId: string | undefined;
                if (fs.existsSync(themeFilesPath)) {
                    try {
                        const themeFiles = JSON.parse(fs.readFileSync(themeFilesPath, 'utf-8')) as { themeId?: string };
                        if (typeof themeFiles.themeId === 'string') {
                            themeId = themeFiles.themeId;
                        }
                    } catch {
                        // Keep dev mode running even when theme-files.json is invalid.
                    }
                }

                const styles: string[] = fs.existsSync(themeFilesPath)
                    ? [`${origin}/theme-scss/all.css`]
                    : [];

                for (const [bundleName, bundle] of Object.entries(bundles)) {
                    const namespace = bundleName === 'Storefront' ? '' : bundleName;
                    const compRoot = path.join(projectRoot, bundle.basePath ?? '', COMPONENTS_PATH);
                    if (!fs.existsSync(compRoot)) continue;

                    const styleFiles = await glob('**/*.{scss,css}', {
                        cwd: compRoot,
                        ignore: ['**/node_modules/**', '**/*.stories.*'],
                    });

                    for (const file of styleFiles) {
                        const namespacedPath = namespace ? `${namespace}/${file}` : file;
                        styles.push(`${origin}${COMP_CSS_PREFIX}${namespacedPath}`);
                    }
                }

                // JS bundle entry URLs — replaces the Webpack hot proxy in dev.
                // Core storefront main.js lives inside the Vite root so it gets a
                // clean URL; plugin entries are outside and use the /@fs/ prefix.
                // Mirrors the webpack.config.js pluginEntries filter: only bundles
                // with a storefront.entryFilePath are included, and technicalName
                // 'storefront' is the core entry, not a plugin.
                const scripts: string[] = [];
                if (fs.existsSync(pluginsJsonPath)) {
                    // Core storefront entry is always first.
                    scripts.push(`${origin}/src/main.js`);

                    for (const [, bundle] of Object.entries(bundles)) {
                        const entryFilePath = bundle.storefront?.entryFilePath;
                        if (!entryFilePath || bundle.technicalName === 'storefront') continue;
                        const absEntry = path.join(projectRoot, bundle.basePath ?? '', entryFilePath);
                        scripts.push(`${origin}/@fs${absEntry}`);
                    }
                }

                const devMap = { imports, styles, scripts, ...(themeId ? { themeId } : {}) };
                fs.mkdirSync(path.dirname(flagFile), { recursive: true });
                fs.writeFileSync(flagFile, JSON.stringify(devMap, null, 2));
                server.config.logger.info(
                    `[sw-dev-import-map] dev flag file written → ${flagFile}`,
                    { timestamp: true },
                );
            };

            // Write the map once the HTTP server is ready.
            server.httpServer?.once('listening', () => void write());

            // Full-reload on component style changes. On add/unlink the dev
            // flag file must be rewritten first so the new <link> list (PHP
            // reads it to render <link rel="stylesheet"> tags) matches the
            // files on disk before the browser reloads.
            const onStyleEvent = (event: 'changed' | 'added' | 'removed', file: string): void => {
                if (!isComponentStyleFile(file)) return;

                const notify = (): void => {
                    server.config.logger.info(
                        `[sw-dev-import-map] component style ${event}: ${path.relative(projectRoot, file)} → full reload`,
                        { timestamp: true },
                    );
                    server.ws.send({ type: 'full-reload' });
                };

                if (event === 'changed') {
                    notify();
                    return;
                }

                // add/remove: regenerate the dev flag file first, then reload.
                void write().then(notify).catch(() => notify());
            };

            server.watcher.on('change', f => onStyleEvent('changed', f));
            server.watcher.on('add', f => onStyleEvent('added', f));
            server.watcher.on('unlink', f => onStyleEvent('removed', f));

            // Clean up on graceful shutdown and common signals.
            server.httpServer?.once('close', () => { cleanup(); });
            process.once('SIGINT', () => { cleanup(); });
            process.once('SIGTERM', () => { cleanup(); });
        },
    };
}
