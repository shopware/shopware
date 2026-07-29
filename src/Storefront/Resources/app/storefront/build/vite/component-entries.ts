import path from 'node:path';
import fs from 'node:fs';
import { glob } from 'tinyglobby';

/**
 * Absolute path to the Shopware project root.
 */
const projectRoot = process.env.PROJECT_ROOT
    ? path.resolve(process.env.PROJECT_ROOT)
    : path.resolve(import.meta.dirname, '../../../../../../../');

const COMPONENTS_PATH = 'Resources/views/components';

type BundleDefinition = {
    basePath?: string;
};

function resolveStorefrontBasePath(): string {
    const pluginsFile = path.resolve(projectRoot, 'var/plugins.json');

    if (fs.existsSync(pluginsFile)) {
        try {
            const bundles = JSON.parse(fs.readFileSync(pluginsFile, 'utf8')) as Record<string, BundleDefinition>;
            const storefrontBasePath = bundles.Storefront?.basePath;

            if (typeof storefrontBasePath === 'string' && storefrontBasePath !== '') {
                return path.resolve(projectRoot, storefrontBasePath);
            }
        } catch {
            // Fall back to the static path if plugins.json is missing/invalid.
        }
    }

    return path.resolve(projectRoot, 'src/Storefront');
}

/**
 * Absolute path to the Storefront core component root.
 */
export const componentRoot = path.resolve(resolveStorefrontBasePath(), COMPONENTS_PATH);

/**
 * Globs all non-test JS/TS component files under componentRoot and returns
 * them as a Rolldown input map keyed by the path-without-extension.
 *
 *   'Sw/Header/Navbar.ts' → { 'Sw/Header/Navbar': '/abs/path/…/Navbar.ts' }
 */
export async function buildComponentEntries(): Promise<Record<string, string>> {
    const files = await glob('**/*.{js,ts}', {
        cwd: componentRoot,
        // The `node_modules` exclude is required because the storefront's
        // postinstall hook symlinks `views/components/node_modules` to the
        // storefront app's `node_modules` so IDEs and tsc can resolve bare
        // specifiers from component files.
        ignore: ['**/node_modules/**', '**/*.test.{js,ts}', '**/*.stories.*'],
    });

    return Object.fromEntries(
        files.map(file => [
            file.replace(/\.(js|ts)$/, ''),
            path.join(componentRoot, file),
        ]),
    );
}

/**
 * Virtual-module id prefix used by the plain-CSS-shim plugin. The prefix
 * starts with NUL so Rolldown treats the id as purely virtual (never resolved
 * against the filesystem) and the suffix ends in `.css` so Vite's CSS plugin
 * recognises the module as CSS.
 */
export const PLAIN_CSS_SHIM_PREFIX = '\0sw-plain-css:';

/**
 * Result of {@link buildComponentStyleEntries}, containing both the direct
 * SCSS entry map and the plain-CSS-shim metadata needed by
 * {@see plainCssShimPlugin}.
 */
export interface ComponentStyleEntries {
    /**
     * Rolldown input map for SCSS entries.
     *
     * The `.scss` extension is intentionally kept in the entry key:
     *   'Sw/Header/Navbar.scss' → { 'Sw/Header/Navbar.scss': '/abs/…/Navbar.scss' }
     *
     * This prevents a silent key collision when a JS/TS file and an SCSS file
     * share the same base name (e.g. `Dusel.js` + `Dusel.scss`). Without the
     * extension the two entries would map to the same key and JavaScript
     * object spread would silently drop the JS entry. The companion
     * `assetFileNames` function in each Vite config strips the `.scss` suffix
     * so the CSS output filename remains clean:
     *
     *   Rolldown asset name: 'Sw/Header/Navbar.scss.css' → output: 'Sw/Header/Navbar-[hash].css'
     */
    scssEntries: Record<string, string>;

    /**
     * Rolldown input map for plain `.css` entries, pointing at virtual module
     * ids that {@see plainCssShimPlugin} loads from disk.
     */
    plainCssEntries: Record<string, string>;

    /**
     * Virtual-id → absolute `.css` path mapping. Pass this directly to
     * {@see plainCssShimPlugin}.
     */
    plainCssShims: Map<string, string>;
}

/**
 * Globs all style component files (`.scss` + plain `.css`) under
 * componentRoot and returns entry maps for both the native-Vite SCSS pipeline
 * and the virtual-CSS-module shim pipeline.
 *
 * A component may declare exactly one style source — either `Foo.scss` or
 * `Foo.css`, never both. Shipping both for the same component is a build
 * error: it's ambiguous which stylesheet the component means.
 */
export async function buildComponentStyleEntries(): Promise<ComponentStyleEntries> {
    const [scssFiles, cssFiles] = await Promise.all([
        glob('**/*.scss', {
            cwd: componentRoot,
            ignore: ['**/node_modules/**', '**/*.stories.*'],
        }),
        glob('**/*.css', {
            cwd: componentRoot,
            ignore: ['**/node_modules/**', '**/*.stories.*'],
        }),
    ]);

    // A component may declare exactly one style source — either .scss or .css,
    // never both. This check runs once per build so it is O(n).
    const scssBasenames = new Set(scssFiles.map(f => f.replace(/\.scss$/, '')));
    for (const cssFile of cssFiles) {
        const base = cssFile.replace(/\.css$/, '');
        if (scssBasenames.has(base)) {
            throw new Error(
                `[component-entries] Component "${base}" has both a .scss and a .css file. `
                + 'A component may declare only one style source.',
            );
        }
    }

    const scssEntries = Object.fromEntries(
        scssFiles.map(file => [file, path.join(componentRoot, file)]),
    );

    const plainCssShims = new Map<string, string>();
    const plainCssEntries: Record<string, string> = {};
    for (const cssFile of cssFiles) {
        const virtualId = `${PLAIN_CSS_SHIM_PREFIX}${cssFile}`;
        plainCssShims.set(virtualId, path.join(componentRoot, cssFile));
        plainCssEntries[cssFile] = virtualId;
    }

    return { scssEntries, plainCssEntries, plainCssShims };
}

