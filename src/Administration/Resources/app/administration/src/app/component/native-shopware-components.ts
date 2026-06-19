/**
 * @sw-package framework
 * @private
 */

import {
    isNativeShopwareComponentName,
    nativeShopwareComponentNames,
    registerNativeShopwareComponentNames,
} from 'src/core/factory/native-component.registry';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export type NativeShopwareComponentLoader = () => Promise<unknown>;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export const nativeShopwareComponents: Record<string, NativeShopwareComponentLoader> = {
    'sw-meteor-entity-data-table': () =>
        import('src/app/component/entity/sw-meteor-entity-data-table/sw-meteor-entity-data-table.vue'),
};

registerNativeShopwareComponentNames(Object.keys(nativeShopwareComponents));

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export { isNativeShopwareComponentName, nativeShopwareComponentNames };
