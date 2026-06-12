/**
 * @sw-package framework
 * @private
 */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export type NativeShopwareComponentLoader = () => Promise<unknown>;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export const nativeShopwareComponents: Record<string, NativeShopwareComponentLoader> = {
    'sw-meteor-entity-data-table': () =>
        import('src/app/component/entity/sw-meteor-entity-data-table/sw-meteor-entity-data-table.vue'),
};

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export const nativeShopwareComponentNames = new Set<string>(Object.keys(nativeShopwareComponents));

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export function isNativeShopwareComponentName(componentName: string): boolean {
    return nativeShopwareComponentNames.has(componentName);
}
