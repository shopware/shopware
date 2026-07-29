import fs from 'node:fs';
import type { Plugin } from 'vite';

/**
 * Virtual-CSS-module bridge for plain `.css` component entries.
 *
 * Rolldown+Vite only register CSS-typed assets in the build manifest when the
 * CSS flows through Vite's "pure CSS chunk" pipeline. SCSS sources hit that
 * path naturally because Vite's SCSS handler turns the `.scss` file into a
 * CSS-typed module whose entire chunk is pure CSS. A raw `.css` file passed
 * directly as a Rolldown input bypasses the pipeline and is emitted as a
 * generic asset with no manifest entry — invisible to the PHP
 * ThemeCompiler import-map aggregation.
 *
 * This plugin routes plain CSS through the same pipeline by exposing each
 * plain `.css` entry as a virtual module whose id ends in `.css`. Vite's CSS
 * plugin recognises the virtual id as CSS and runs it through PostCSS; the
 * `load()` hook serves the real file contents from disk. The result is a
 * pure-CSS chunk whose facade module id matches `isCSSRequest`, so the asset
 * name retains its namespace prefix (`Ns/Foo.css` → `Ns/Foo-[hash].css`) and
 * a proper manifest entry is written. The virtual module itself never lands
 * on disk.
 *
 * @param shims Map of virtual-id → absolute `.css` file path. The caller is
 * expected to use the prefix convention `\0sw-plain-css:` to keep
 * the virtual ids stable, unique, and distinguishable from
 * real-file ids in build logs.
 */
export function plainCssShimPlugin(shims: Map<string, string>): Plugin {
    return {
        name: 'sw-plain-css-shim',
        resolveId(id) {
            return shims.has(id) ? id : null;
        },
        load(id) {
            const absCssPath = shims.get(id);
            if (absCssPath === undefined) return null;
            return fs.readFileSync(absCssPath, 'utf8');
        },
    };
}
