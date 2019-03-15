/**
 * @module core/factory/context
 * @type factory
 */
export default function createContext(context = {}) {
    const isDevMode = (process.env.NODE_ENV !== 'production');
    const installationPath = getInstallationPath(context, isDevMode);
    const apiPath = `${installationPath}/api`;

    Object.assign(context, {
        installationPath,
        environment: process.env.NODE_ENV,
        apiPath: apiPath,
        apiResourcePath: `${apiPath}/v1`,
        assetsPath: getAssetsPath(installationPath, isDevMode)
    });

    if (isDevMode) {
        Object.assign(context, {
            systemLanguageId: '20080911ffff4fffafffffff19830531',
            defaultLanguageIds: ['20080911ffff4fffafffffff19830531', '00e84bd18c574a6ca748ac0db17654dc'],
            liveVersionId: '20080911ffff4fffafffffff19830531'
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
