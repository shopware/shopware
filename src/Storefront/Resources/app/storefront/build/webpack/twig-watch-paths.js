/**
 * @sw-package framework
 */
const fs = require('node:fs');
const path = require('node:path');

const STOREFRONT_VIEW_DIRECTORIES = ['storefront', 'components'];

/**
 * Returns the glob patterns of all Twig files the hot mode watches.
 *
 * The view directories come from `var/plugins.json`, which lists every bundle, including the Storefront itself.
 * Core bundles are watched only when they contain Storefront templates, so bundles with mail or document templates are skipped.
 * Extensions can render templates from any directory below their views, so their view directories are always watched.
 * A bundle counts as core when it lives in the same directory as the Storefront bundle, like `src/` or `vendor/shopware/`.
 *
 * @param {string} projectRootPath
 * @param {Object<string, {technicalName: string, basePath: string, views?: string[]}>} pluginDefinition
 * @param {boolean} includeExtensions - `false` watches the Storefront bundle only
 * @returns {string[]}
 */
module.exports = function getTwigWatchPaths(projectRootPath, pluginDefinition, includeExtensions = true) {
    const paths = new Set();
    const storefrontDefinition = Object.values(pluginDefinition).find((definition) => {
        return definition.technicalName === 'storefront';
    });
    const coreRootPath = storefrontDefinition
        ? path.dirname(path.resolve(projectRootPath, storefrontDefinition.basePath))
        : null;

    Object.values(pluginDefinition).forEach((definition) => {
        if (!includeExtensions && definition.technicalName !== 'storefront') {
            return;
        }

        const basePath = path.resolve(projectRootPath, definition.basePath);
        const isCoreBundle = coreRootPath !== null && basePath.startsWith(`${coreRootPath}${path.sep}`);

        (definition.views || []).forEach((view) => {
            const viewPath = path.resolve(basePath, view);
            const hasStorefrontViews = STOREFRONT_VIEW_DIRECTORIES.some((directory) => {
                return fs.existsSync(path.join(viewPath, directory));
            });

            if (!isCoreBundle || hasStorefrontViews) {
                paths.add(`${viewPath}/**/*.twig`);
            }
        });
    });

    return [...paths];
};
