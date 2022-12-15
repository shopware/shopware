/**
 * @package admin
 *
 * @deprecated tag:v6.6.0 - Will be private
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

    // set initial context
    Shopware.State.commit('context/setApiInstallationPath', installationPath);
    Shopware.State.commit('context/setApiApiPath', apiPath);
    Shopware.State.commit('context/setApiApiResourcePath', `${apiPath}`);
    Shopware.State.commit('context/setApiAssetsPath', getAssetsPath(context.assetPath, isDevMode));
    Shopware.State.commit('context/setApiLanguageId', languageId);
    Shopware.State.commit('context/setApiInheritance', false);

    if (isDevMode) {
        Shopware.State.commit('context/setApiSystemLanguageId', Defaults.systemLanguageId);
        Shopware.State.commit('context/setApiLiveVersionId', Defaults.versionId);
    }

    // assign unknown context information
    Object.entries(context).forEach(([key, value]) => {
        Shopware.State.commit('context/addApiValue', { key, value });
    });

    return Shopware.Context.api;
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
    if (context.schemeAndHttpHost?.length) {
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
        return '/bundles/';
    }

    return `${installationPath}/bundles/`;
}
