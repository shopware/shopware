/**
 * @sw-package framework
 * @private
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
