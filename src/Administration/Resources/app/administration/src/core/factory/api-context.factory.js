import useContext from '../../app/composables/use-context';

/**
 * @sw-package framework
 *
 * @private
 * @module core/factory/context
 * @param {Object} context
 * @type factory
 */
export default function createContext(context = {}) {
    const Defaults = Shopware.Defaults;
    const isDevMode = process.env.NODE_ENV !== 'production';
    const installationPath = getInstallationPath(context, isDevMode);
    const apiPath = `${installationPath}/api`;

    const languageId = localStorage.getItem('sw-admin-current-language') || Defaults.systemLanguageId;

    // set initial context
    const contextStore = useContext();
    contextStore.api.installationPath = installationPath;
    contextStore.api.apiPath = apiPath;
    contextStore.api.apiResourcePath = `${apiPath}`;
    contextStore.api.assetsPath = getAssetsPath(context.assetPath, isDevMode);
    contextStore.api.languageId = languageId;
    contextStore.api.inheritance = false;

    if (isDevMode) {
        contextStore.api.systemLanguageId = Defaults.systemLanguageId;
        contextStore.api.liveVersionId = Defaults.versionId;
    }

    // assign unknown context information
    Object.entries(context).forEach(
        ([
            key,
            value,
        ]) => {
            contextStore.addApiValue({ key, value });
        },
    );

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
