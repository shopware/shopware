const path = require('path');

module.exports = {
    getBuildPath,
    getPublicPath,
    getProjectRootPath,
    getPath,
    getMode,
    getOutputPath,
    getAppUrl,
    getScssEntryByName,
    isHotModuleReplacementMode,
    isDevelopmentEnvironment,
    isProductionEnvironment,
};

/**
 * Returns the output path for the build.
 *
 * @returns {String}
 */
function getOutputPath() {
    return '.';
}

/**
 * Returns the full path to the project root directory
 *
 * @return {String}
 */
function getProjectRootPath() {
    if (!process.env.PROJECT_ROOT) {
        process.env.PROJECT_ROOT = '../../../../..';
    }

    return path.resolve(process.env.PROJECT_ROOT);
}

/*
 * Returns the public path, depending on the environment
 *
 * @return {String}
 */
function getPublicPath() {
    return `${getHostname()}${(isHotModuleReplacementMode()) ? ':9999' : ''}/`;
}

/**
 * Returns the build directory
 *
 * @return {String}
 */
function getBuildPath() {
    return path.join(__dirname, '..', 'dist');
}

/**
 * Returns the mode of the current build
 *
 * @return {String}
 */
function getMode() {
    return process.env.MODE;
}

/**
 * Truthy if the app is the hot module replacement mode
 *
 * @return {Boolean}
 */
function isHotModuleReplacementMode() {
    return getMode() === 'hot';
}

/**
 * Returns the node environment of the application for the current build
 *
 * @return {String}
 */
function getEnvironment() {
    return process.env.NODE_ENV;
}

/**
 * Truthy if the app is the development mode
 *
 * @return {Boolean}
 */
function isDevelopmentEnvironment() {
    return getEnvironment() === 'development';
}

/**
 * Truthy if the app is the hot module replacement mode
 * @return {boolean}
 */
function isProductionEnvironment() {
    return getEnvironment() === 'production';
}

/**
 * Returns the public application URL
 * @return {string}
 */
function getAppUrl() {
    return process.env.APP_URL;
}

/**
 * Returns the public application URL without port number
 * @return {string}
 */
function getHostname() {
    try {
        const { protocol, hostname } = new URL(process.env.APP_URL);
        return `${protocol}//${hostname}`;
    } catch (e) {
        return undefined;
    }
}

/**
 * Returns a full path relative. The provided directory parameter has to be relative to root directory of the storefront
 *
 * @param {String} dir
 * @returns {String}
 */
function getPath(dir) {
    const basePath = path.join(__dirname, '..');
    if (dir) {
        return path.join(basePath, dir);
    }

    return basePath;
}

/**
 * Returns the entry point config object matching to the given filepath name.
 *
 * @param {Array} styles
 * @param {String} fileName
 * @returns {Object|undefined}
 */
function getScssEntryByName(styles, fileName) {
    return styles.find((item) => {
        return item.filepath.includes(fileName);
    });
}
