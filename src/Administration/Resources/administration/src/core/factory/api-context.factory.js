/**
 * @module core/factory/context
 * @param {Object} context
 * @type factory
 */
export default function createContext(context = {}) {
    const Defaults = Shopware.Defaults;
    const isDevMode = (process.env.NODE_ENV !== 'production');
    const installationPath = getInstallationPath(context, isDevMode);
    const apiPath = `${installationPath}/api`;

    const languageId = localStorage.getItem('sw-admin-current-language') || Defaults.systemLanguageId;

    Object.assign(context, {
        installationPath,
        apiPath: apiPath,
        apiResourcePath: `${apiPath}/v1`,
        assetsPath: getAssetsPath(installationPath, isDevMode),
        languageId: languageId,
        inheritance: false
    });

    if (isDevMode) {
        Object.assign(context, {
            systemLanguageId: Defaults.systemLanguageId,
            defaultLanguageIds: Defaults.defaultLanguageIds,
            liveVersionId: Defaults.versionId
        });
    }

    return context;
}

/**
 * Provides the installation path of the application. The path provides the scheme, host and sub directory.
 *
 * @param {Object} context
 * @param {Boolean} isDevMode
 * @returns {string}
 */
function getInstallationPath(context, isDevMode) {
    if (isDevMode) {
        return '';
    }

    let fullPath = '';
    if (context.schemeAndHttpHost && context.schemeAndHttpHost.length) {
        fullPath = `${context.schemeAndHttpHost}${context.basePath}`;
    }

    return fullPath;
}

/**
 * Provides the path to the assets directory.
 *
 * @param {String} installationPath
 * @param {Boolean} isDevMode
 * @returns {string}
 */
function getAssetsPath(installationPath, isDevMode) {
    if (isDevMode) {
        return '';
    }

    return `${installationPath}/bundles/`;
}
