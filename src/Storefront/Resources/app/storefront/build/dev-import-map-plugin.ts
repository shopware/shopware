import path from 'node:path';
import fs from 'node:fs';
import type { Plugin, ViteDevServer } from 'vite';
import { glob } from 'tinyglobby';

type BundleEntry = {
    basePath?: string;
    components?: { path: string };
};

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
 * Vite plugin that manages the component dev import map flag file.
 *
 * When the Vite dev server starts it writes a **complete, valid import map**
 * to `var/cache/storefront_components.dev.json`.  PHP detects the file and
 * uses its contents directly as the `<script type="importmap">` payload —
 * no URL rewriting is needed on the PHP side because the map already points
 * every bare specifier (`shopware`, `Sw:Header:Navbar`, …) to the running
 * dev server.
 *
 * When the dev server stops or crashes the file is removed.  The Storefront
 * then transparently falls back to the production import map that was
 * compiled by `theme:compile`.
 */
export function devImportMapPlugin(projectRoot: string): Plugin {
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

        async configureServer(server: ViteDevServer) {
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

                // All component files from every registered bundle.
                const pluginsJsonPath = path.join(projectRoot, 'var/plugins.json');
                const bundles = fs.existsSync(pluginsJsonPath)
                    ? (JSON.parse(fs.readFileSync(pluginsJsonPath, 'utf-8')) as Record<string, BundleEntry>)
                    : {};

                for (const [bundleName, bundle] of Object.entries(bundles)) {
                    if (!bundle.components?.path) continue;

                    // The core Storefront bundle uses bare component names
                    // (e.g. 'Sw:Header:Navbar'); all other bundles are prefixed
                    // with their bundle name as a namespace.
                    const namespace = bundleName === 'Storefront' ? undefined : bundleName;
                    const compRoot = path.join(projectRoot, bundle.basePath ?? '', bundle.components.path);

                    if (!fs.existsSync(compRoot)) continue;

                    const files = await glob('**/*.{js,ts}', {
                        cwd: compRoot,
                        ignore: ['**/*.test.{js,ts}', '**/*.stories.*'],
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

                const devMap = { imports };
                fs.mkdirSync(path.dirname(flagFile), { recursive: true });
                fs.writeFileSync(flagFile, JSON.stringify(devMap, null, 2));
                server.config.logger.info(
                    `[sw-dev-import-map] dev import map written → ${flagFile}`,
                    { timestamp: true },
                );
            };

            // Write the map once the HTTP server is ready.
            server.httpServer?.once('listening', () => void write());

            // Clean up on graceful shutdown and common signals.
            server.httpServer?.once('close', cleanup);
            process.once('exit', cleanup);
            process.once('SIGINT', () => { cleanup(); process.exit(0); });
            process.once('SIGTERM', () => { cleanup(); process.exit(0); });
        },
    };
}
