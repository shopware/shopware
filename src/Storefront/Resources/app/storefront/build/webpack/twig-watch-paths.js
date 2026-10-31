/**
 * @sw-package framework
 */
const fs = require('node:fs');
const path = require('node:path');

const STOREFRONT_VIEW_DIRECTORIES = ['storefront', 'components'];

/**
 * Returns the glob patterns of all Twig files the hot mode watches.
 *
 * The view directories come from `var/plugins.json`, which lists every bundle, including the Storefront itself, and every active app.
 * Only view directories with Storefront templates are watched, so Core bundles with mail or document templates are skipped.
 *
 * @param {string} projectRootPath
 * @param {Object<string, {technicalName: string, basePath: string, views?: string[]}>} pluginDefinition
 * @param {boolean} includeExtensions - `false` watches the Storefront bundle only
 * @returns {string[]}
 */
module.exports = function getTwigWatchPaths(projectRootPath, pluginDefinition, includeExtensions = true) {
    const paths = new Set();

    Object.values(pluginDefinition).forEach((definition) => {
        if (!includeExtensions && definition.technicalName !== 'storefront') {
            return;
        }

        (definition.views || []).forEach((view) => {
            const viewPath = path.resolve(projectRootPath, definition.basePath, view);
            const hasStorefrontViews = STOREFRONT_VIEW_DIRECTORIES.some((directory) => {
                return fs.existsSync(path.join(viewPath, directory));
            });

            if (hasStorefrontViews) {
                paths.add(`${viewPath}/**/*.twig`);
            }
        });
    });

    return [...paths];
};
