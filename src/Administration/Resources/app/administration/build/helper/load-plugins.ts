import path from "path";
import fs from "fs";

/**
 * Create an array with information about all injected plugins.
 *
 * The given structure looks like this:
 * [
 *   {
 *      name: 'SwagExtensionStore',
 *      technicalName: 'swag-extension-store',
 *      basePath: '/Users/max.muster/Sites/shopware/custom/plugins/SwagExtensionStore/src',
 *      path: '/Users/max.muster/Sites/shopware/custom/plugins/SwagExtensionStore/src/Resources/app/administration/src',
 *      filePath: '/Users/max.muster/Sites/shopware/custom/plugins/SwagExtensionStore/src/Resources/app/administration/src/main.js',
 *      webpackConfig: '/Users/max.muster/Sites/shopware/custom/plugins/SwagExtensionStore/src/Resources/app/administration/build/webpack.config.js'
 *   },
 *    ...
 * ]
 */
export default function loadPlugins() {
    const pluginFile = path.resolve(process.env.PROJECT_ROOT, 'var/plugins.json');

    if (!fs.existsSync(pluginFile)) {
        throw new Error(`The file ${pluginFile} could not be found. Try bin/console bundle:dump to create this file.`);
    }

    const pluginDefinition = JSON.parse(fs.readFileSync(pluginFile, 'utf8'));

    return Object.entries(pluginDefinition)
        .filter(([name, definition]) => !!definition.administration && !!definition.administration.entryFilePath && !process.env.hasOwnProperty(`SKIP_${definition.technicalName.toUpperCase().replace(/-/g, '_')}`))
        .map(([name, definition]) => {
            const technicalName = definition.technicalName || name.replace(/([a-z])([A-Z])/g, '$1-$2').toLowerCase();
            const htmlFilePath = path.resolve(process.env.PROJECT_ROOT, definition.basePath, definition.administration.path, '..', 'index.html');
            const hasHtmlFile = fs.existsSync(htmlFilePath);

            return {
                name,
                technicalName: technicalName,
                technicalFolderName: technicalName.replace(/(-)/g, '').toLowerCase(),
                basePath: path.resolve(process.env.PROJECT_ROOT, definition.basePath),
                path: path.resolve(process.env.PROJECT_ROOT, definition.basePath, definition.administration.path),
                filePath: path.resolve(process.env.PROJECT_ROOT, definition.basePath, definition.administration.entryFilePath),
                hasHtmlFile,
                webpackConfig: definition.administration.webpack ? path.resolve(process.env.PROJECT_ROOT, definition.basePath, definition.administration.webpack) : null,
            };
        });
}
