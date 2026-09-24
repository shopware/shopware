/**
 * @sw-package framework
 */

type ShopwareSetupMode = 'base' | 'override';

type InferredShopwareSetup = {
    mode: ShopwareSetupMode;
    componentName: string;
};

const OVERRIDE_SUFFIX = '.override.vue';
const BASE_SUFFIX = '.vue';

/** Multi-segment lowercase kebab-case, e.g. `sw-product-detail`. */
const COMPONENT_NAME_PATTERN = /^[a-z][a-z0-9]*(-[a-z0-9]+)+$/;

/** Every binding the transform generates starts with this prefix, so authors must not use it. */
const RESERVED_BINDING_PREFIX = '__swSetup';

/** The slot-scope key that carries override-local bindings into `<sw-block extends>` content. */
const OVERRIDE_LOCAL_STATE_KEY = '__swOverride';

function pathSegments(filename: string): string[] {
    return filename.split(/[?#]/, 1)[0].replace(/\\/g, '/').split('/').filter(Boolean);
}

/**
 * - `sw-thing.vue` / `sw-thing/index.vue` -> base component `sw-thing`
 * - `sw-thing.override.vue` / `sw-thing/index.override.vue` -> override of `sw-thing`
 */
function inferShopwareSetupFromFilename(filename: string): InferredShopwareSetup {
    const segments = pathSegments(filename);
    const file = segments[segments.length - 1] ?? filename;
    const mode: ShopwareSetupMode = file.endsWith(OVERRIDE_SUFFIX) ? 'override' : 'base';
    const suffix = mode === 'override' ? OVERRIDE_SUFFIX : BASE_SUFFIX;

    if (file === `index${suffix}`) {
        return { mode, componentName: segments[segments.length - 2] ?? file };
    }

    return { mode, componentName: file.endsWith(suffix) ? file.slice(0, -suffix.length) : file };
}

function isDependencyFile(filename: string): boolean {
    return filename.replace(/\\/g, '/').includes('/node_modules/');
}

function isReservedBindingName(name: string): boolean {
    return name.startsWith(RESERVED_BINDING_PREFIX) || name === OVERRIDE_LOCAL_STATE_KEY;
}

/**
 * @private
 */
export {
    type InferredShopwareSetup,
    type ShopwareSetupMode,
    COMPONENT_NAME_PATTERN,
    OVERRIDE_LOCAL_STATE_KEY,
    RESERVED_BINDING_PREFIX,
    inferShopwareSetupFromFilename,
    isDependencyFile,
    isReservedBindingName,
};
