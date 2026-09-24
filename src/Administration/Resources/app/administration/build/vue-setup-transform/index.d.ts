/**
 * @sw-package framework
 */

/**
 * Types for the CommonJS bridge (`index.js`), derived from the implementation so they cannot drift.
 */

export {
    COMPONENT_NAME_PATTERN,
    OVERRIDE_LOCAL_STATE_KEY,
    RESERVED_BINDING_PREFIX,
    ShopwareSetupTransformError,
    analyzeShopwareSetupSfc,
    inferShopwareSetupFromFilename,
    isDependencyFile,
    isReservedBindingName,
    transformShopwareSetupSfc,
    validateShopwareSetupSfc,
} from './index';

export type { InferredShopwareSetup, ShopwareSetupMode, ShopwareSetupTransformResult } from './index';
