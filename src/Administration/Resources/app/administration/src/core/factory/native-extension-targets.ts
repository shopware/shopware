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
 *
 * Private in the sense that no extension author writes this call by hand - but the Vite override
 * transform compiles it into every shipped plugin bundle, so the signature is effectively public
 * once a plugin is built. A changed payload shape breaks every extension already in the wild, and
 * the optional call in the generated `<script>` only guards against the function missing entirely,
 * not against it expecting something else. Extend `NativeExtensionTargets` with optional properties;
 * do not repurpose or remove the existing ones.
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
