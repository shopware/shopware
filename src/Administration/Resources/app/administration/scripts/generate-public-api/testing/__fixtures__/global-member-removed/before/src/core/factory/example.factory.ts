/**
 * @sw-package framework
 */
const registry = new Map<string, unknown>();

function register(name: string, example: unknown): boolean {
    registry.set(name, example);

    return true;
}

function getByName(name: string): unknown {
    return registry.get(name);
}

/**
 * @private
 */
function getRegistry(): Map<string, unknown> {
    return registry;
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    register,
    getByName,
    getRegistry,
};
