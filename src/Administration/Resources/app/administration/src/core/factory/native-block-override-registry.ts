/**
 * @sw-package framework
 * @private
 *
 * Registry of the block names that native overrides (`<sw-block extends="...">`) contribute to.
 *
 * This is the gate for the Twig → Native Block shim: a Twig component template keeps a block boundary
 * as a real `<sw-block>` element only when an override actually targets that block. Core ships ~8.000
 * `{% block %}` markers, so wrapping them unconditionally would cost a component instance per marker;
 * gating on this registry keeps the cost proportional to the extensions installed and leaves every
 * untargeted template byte-identical to before.
 *
 * Entries must be registered at module import time — `resolveComponentTemplates()` renders every Twig
 * template during boot, long before the hidden override components in `sw-admin` mount.
 */

const extendedBlockNames = new Set<string>();

/**
 * Registers the block names an override component extends.
 *
 * Use it from an extension entry file (or from generated build output) before the administration boots.
 *
 * @example
 * Shopware.Component.registerNativeBlockOverrides(['sw_page_smart_bar_content_header']);
 *
 * @private
 */
export function registerNativeBlockOverrides(blockNames: string[]): void {
    blockNames.forEach((blockName) => extendedBlockNames.add(blockName));
}

/**
 * Checks whether any native override contributes to `blockName`.
 *
 * Use it to decide whether a Twig block boundary is worth materializing as a `<sw-block>` element.
 *
 * @example
 * const wrap = hasNativeBlockOverride('sw_page_content');
 *
 * @private
 */
export function hasNativeBlockOverride(blockName: string): boolean {
    return extendedBlockNames.has(blockName);
}

/**
 * Checks whether any native block override is registered at all.
 *
 * Use it as the cheap early-out before walking a template's token tree.
 *
 * @example
 * if (!hasAnyNativeBlockOverride()) return null;
 *
 * @private
 */
export function hasAnyNativeBlockOverride(): boolean {
    return extendedBlockNames.size > 0;
}

/**
 * Empties the registry.
 *
 * Use it in tests between cases; the administration itself never unregisters.
 *
 * @example
 * afterEach(() => resetNativeBlockOverrides());
 *
 * @private
 */
export function resetNativeBlockOverrides(): void {
    extendedBlockNames.clear();
}
