/**
 * @module core/factory/directive
 */
import { warn } from 'src/core/service/utils/debug.utils';

export default {
    registerDirective,
    getDirectiveByName,
    getDirectiveRegistry,
};

/**
 * Registry which holds all registered directives.
 *
 * @type {Map<String, Object>}
 */
const directiveRegistry = new Map();

/**
 * Registers a new directive.
 *
 * @param {String} name
 * @param {Object} [directive={}]
 * @returns {boolean}
 */
function registerDirective(name, directive = {}) {
    if (!name || !name.length) {
        warn('DirectiveFactory', 'A directive always needs a name.', directive);
        return false;
    }

    if (directiveRegistry.has(name)) {
        warn('DirectiveFactory', `A directive with the name ${name} already exists.`, directive);
        return false;
    }

    directiveRegistry.set(name, directive);

    return true;
}

/**
 * Get a directive by its name.
 *
 * @param {String} name
 * @returns {Object | undefined}
 */
function getDirectiveByName(name) {
    return directiveRegistry.get(name);
}

/**
 * Get the complete registry of directives.
 *
 * @returns {Map<String, Object>}
 */
function getDirectiveRegistry() {
    return directiveRegistry;
}
