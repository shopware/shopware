/**
 * @sw-package framework
 */
const registry = new Map<string, unknown>();

function register(name: string, example: unknown, options: { override: boolean }): boolean {
    if (options.override || !registry.has(name)) {
        registry.set(name, example);
    }

    return true;
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    register,
};
