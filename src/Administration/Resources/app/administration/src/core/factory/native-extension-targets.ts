/**
 * @sw-package framework
 * @private
 *
 * @experimental stableVersion:v6.9.0 feature:ADMIN_COMPOSITION_API_EXTENSION_SYSTEM
 */

type NativeExtensionTargets = {
    component: string;
    blocks?: string[];
};

const nativeBlockExtensionTargets = new Set<string>();

/**
 * @private
 */
export function registerNativeExtensionTargets(targets: NativeExtensionTargets): void {
    targets.blocks?.forEach((blockName) => nativeBlockExtensionTargets.add(blockName));
}

/**
 * @private
 */
export function getNativeBlockExtensionTargets(): ReadonlySet<string> {
    return nativeBlockExtensionTargets;
}
