/**
 * @sw-package framework
 * @private
 */

const nativeShopwareComponentNames = new Set<string>();

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export function registerNativeShopwareComponentNames(componentNames: Iterable<string>): void {
    Array.from(componentNames).forEach((componentName) => {
        nativeShopwareComponentNames.add(componentName);
    });
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export function isNativeShopwareComponentName(componentName: string): boolean {
    return nativeShopwareComponentNames.has(componentName);
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export { nativeShopwareComponentNames };
