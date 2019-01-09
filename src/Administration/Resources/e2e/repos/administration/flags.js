global.flags = {
    getAllActive,
    getAllInactive,
    isActive,
    runIfIsActive,
    getCache
};

const LoggingHelper = require('../../common/helper/cliOutputHelper');
const path = require('path');
const fs = require('fs');
const process = require('process');

/** @type {String} Absolute path to feature flag configuration file */
const filePath = resolveFromRootPath(`var/config_administration_features.json`);

/** @type {Object} Feature flags from file */
const flags = readFlagsFromFile(filePath);

/** @type {Map} Cache for the flags */
const $cache = fillFeatureFlagsCache(new Map(), flags);

renderAvailableFlags($cache);

/**
 * Runs the callback function if all flags are active. Otherwise it disables the test.
 *
 * @param {String|Array} flags
 * @param {Function} fn
 * @returns {Function|Boolean}
 */
function runIfIsActive(flags, fn) {
    if (isActive(flags)) {
        return fn;
    }

    return !fn;
}

/**
 * Returns the registered active or inactive flags, depending on the provided status
 *
 * @param {Boolean} [status=true]
 * @returns {Array}
 */
function getByStatus(status = true) {
    const flags = [];
    $cache.forEach((value, key) => {
        if (value === !status) {
            return;
        }
        flags.push(key);
    });
    return flags;
}

/**
 * Returns all active flags
 *
 * @returns {Array}
 */
function getAllActive() {
    return getByStatus(true);
}

/**
 * Returns all inactive flags
 *
 * @returns {Array}
 */
function getAllInactive() {
    return getByStatus(false);
}

/**
 * Returns if a flag is active. If an array is provided, each of the flags needs to be active to get an truthy result.
 * If a flag is non-existent in the system, the flag was removed probably and the flag is assumed to be active then.
 * Otherwise the method returns a falsy result.
 *
 * @param {Array|String} flags - Flags which should be checked
 * @returns {Boolean}
 */
function isActive(flags) {
    if (Array.isArray(flags)) {
        let allFlagsActive = true;
        flags.forEach((flag) => {
            // The flag is non existent in the system, the test should run then
            if (!$cache.has(flag)) {
                return;
            }
            if (allFlagsActive) {
                allFlagsActive = $cache.get(flag);
            }
        });

        return allFlagsActive;
    }
    if (!$cache.has(flags)) {
        return true;
    }

    return $cache.get(flags);
}

/**
 * Reads flags from a file and parses the result as JSON.
 *
 * @param {String} configurationFile - Absolute path to the configuration file
 * @returns {Object}
 */
function readFlagsFromFile(configurationFile) {
    let flags = fs.readFileSync(configurationFile, 'UTF-8');
    return JSON.parse(flags);
}

/**
 * Fills the provided cache with the flags.
 *
 * @param {Map} cache
 * @param {Object} flags
 * @returns {Map}
 */
function fillFeatureFlagsCache(cache, flags) {
    Object.keys(flags).forEach((key) => {
        cache.set(key, flags[key]);
    });

    return cache;
}

/**
 * Resolves a given directory from the root path of the project
 *
 * @param {String} directory
 * @returns {String}
 */
function resolveFromRootPath(directory) {
    return path.join(process.env.PROJECT_ROOT, directory);
}

/**
 * Returns the feature flag cache
 * @returns {Map}
 */
function getCache() {
    return $cache;
}

/**
 * Renders an header to stdout including information about the available flags.
 *
 * @param {Map} $cache
 * @returns {void}
 */
function renderAvailableFlags($cache) {
    const loggingHelper = new LoggingHelper();
    loggingHelper.createCliEntry('Available feature flags', 'title');

    $cache.forEach((value, key) => {
        loggingHelper.createCliEntry(`• ${value ? '✓' : '✖'} - ${key}`);
    });
}