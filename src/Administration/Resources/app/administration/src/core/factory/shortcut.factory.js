/**
 * @package admin
 *
 * @module core/factory/shortcut
 */
import { warn } from 'src/core/service/utils/debug.utils';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    getPathByCombination,
    getShortcutRegistry,
    register,
};

/**
 * Registry which holds all shortcuts including the interface translations
 *
 * @type {Map}
 */
const shortcutRegistry = new Map();

/**
 * Get the path for a specific combination
 *
 * @param {String} combination
 * @returns {Boolean|String}
 */
function getPathByCombination(combination) {
    if (!shortcutRegistry.has(combination)) {
        return false;
    }

    return shortcutRegistry.get(combination);
}

/**
 * Get the complete shortcut registry
 * @returns {Map}
 */
function getShortcutRegistry() {
    return shortcutRegistry;
}

/**
 * Registers a new shortcut
 *
 * @param {String} combination
 * @param {String} [path='']
 * @returns {Boolean|String}
 */
function register(combination, path = '') {
    if (!combination || !combination.length) {
        warn('ShortcutFactory', "A combination can't be blank.");
        return false;
    }

    if (shortcutRegistry.has(combination)) {
        warn('ShortcutFactory', `The combination "${combination}" is registered already.`);

        return false;
    }

    shortcutRegistry.set(combination, path);

    return combination;
}
