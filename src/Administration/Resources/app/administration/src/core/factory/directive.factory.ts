/**
 * @package admin
 *
 * @module core/factory/directive
 */
import { warn } from 'src/core/service/utils/debug.utils';
import type { FunctionDirective, ObjectDirective } from 'vue';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    registerDirective,
    getDirectiveByName,
    getDirectiveRegistry,
};

/**
 * Registry which holds all registered directives.
 */
const directiveRegistry = new Map<string, FunctionDirective | ObjectDirective>();

/**
 * Registers a new directive.
 */
function registerDirective(name: string, directive: FunctionDirective | ObjectDirective = {}): boolean {
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
 */
function getDirectiveByName(name: string) {
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
