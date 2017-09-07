export default function createContext(context) {
    const installationPath = getInstallationPath(context);
    const isDevMode = (process.env.NODE_ENV !== 'production');

    return {
        installationPath,
        apiPath: getApiPath(installationPath, isDevMode),
        assetsPath: getAssetsPath(installationPath, isDevMode)
    };
}

function getInstallationPath(context) {
    if (process.env.NODE_ENV !== 'production') {
        return '';
    }

    let fullPath = '';
    if (context.schemeAndHttpHost && context.schemeAndHttpHost.length) {
        fullPath = `${context.schemeAndHttpHost}${context.basePath}`;
    }

    return fullPath;
}

function getApiPath(installationPath, isDevPath) {
    if (isDevPath) {
        return '';
    }

    return `${installationPath}/api`;
}

function getAssetsPath(installationPath, isDevPath) {
    if (isDevPath) {
        return '';
    }

    return `${installationPath}/bundles/nexus`;
}
