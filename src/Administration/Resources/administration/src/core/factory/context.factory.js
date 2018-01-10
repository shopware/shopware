/**
 * @module core/factory/context
 * @type factory
 */
export default function createContext(context) {
    const isDevMode = (process.env.NODE_ENV !== 'production');
    const installationPath = getInstallationPath(context, isDevMode);

    return {
        installationPath,
        apiPath: getApiPath(installationPath, isDevMode),
        assetsPath: getAssetsPath(installationPath, isDevMode)
    };
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
 * Provides the full path to the API end point of the application
 *
 * @param {String} installationPath
 * @param {Boolean} isDevMode
 * @returns {string}
 */
function getApiPath(installationPath, isDevMode) {
    if (isDevMode) {
        installationPath = process.env.BASE_PATH;
    }

    return `${installationPath}/api`;
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

    return `${installationPath}/bundles/administration`;
}
