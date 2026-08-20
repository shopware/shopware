/**
 * @sw-package framework
 * @private
 *
 * Boot-time registry of the components and blocks that native `.override.vue` extensions target.
 *
 * The override lowering emits one `registerNativeExtensionTargets(...)` call per override SFC into a
 * module-scope `<script>` block, so every target is known once the plugin entries are imported — which
 * happens before `resolveComponentTemplates()` and before the first component is built. That ordering is
 * what lets the Twig bridge decide, at boot, which legacy components need a compatibility wrapper.
 */

/**
 * Describes the extension surface one `.override.vue` file claims.
 *
 * `component` is the override's target component (derived from the filename), `blocks` are the static
 * `<sw-block extends="...">` names in its template.
 *
 * @example
 * registerNativeExtensionTargets({ component: 'sw-product-detail', blocks: ['sw_product_detail_base'] });
 */
type NativeExtensionTargets = {
    component: string;
    blocks?: string[];
};

const nativeBlockExtensionTargets = new Set<string>();
const nativeSetupExtensionTargets = new Set<string>();

/**
 * @private
 *
 * Records the component and block names one native override file extends.
 *
 * Generated code calls this at module scope; author code never does.
 *
 * @example
 * Shopware.Component.registerNativeExtensionTargets({ component: 'sw-product-detail' });
 */
export function registerNativeExtensionTargets(targets: NativeExtensionTargets): void {
    if (targets.component) {
        nativeSetupExtensionTargets.add(targets.component);
    }

    targets.blocks?.forEach((blockName) => nativeBlockExtensionTargets.add(blockName));
}

/**
 * @private
 *
 * Lists every block name a native override extends.
 *
 * Use it when deciding which legacy Twig blocks need a `sw-native-block-host` wrapper.
 *
 * @example
 * getNativeBlockExtensionTargets().has('sw_product_detail_base');
 */
export function getNativeBlockExtensionTargets(): ReadonlySet<string> {
    return nativeBlockExtensionTargets;
}

/**
 * @private
 *
 * Reports whether a native override registers setup state for `componentName`.
 *
 * Use it in the component factory to decide whether an Options API base has to be converted into an
 * extendable setup component.
 *
 * @example
 * hasNativeSetupExtensionTarget('sw-product-detail');
 */
export function hasNativeSetupExtensionTarget(componentName: string): boolean {
    return nativeSetupExtensionTargets.has(componentName);
}

/**
 * @private
 *
 * Clears the registry. Test teardown only — never call this in production code.
 *
 * @example
 * resetNativeExtensionTargets();
 */
export function resetNativeExtensionTargets(): void {
    nativeBlockExtensionTargets.clear();
    nativeSetupExtensionTargets.clear();
}
