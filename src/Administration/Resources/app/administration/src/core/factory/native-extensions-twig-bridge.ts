/**
 * @sw-package framework
 * @private
 *
 * Native → Twig Extension Bridge.
 *
 * The mirror image of the Twig → Native Block Runtime Adapter: that adapter keeps legacy `{% block %}`
 * overrides working on migrated components, this bridge keeps native `.override.vue` extensions working
 * on components that are *not* migrated yet. It has one entry point per extension channel.
 *
 * **Template channel.** One synthetic Twig override per targeted block, at the highest possible override
 * index so it merges last. `{% parent %}` inlines the block body — including every legacy plugin override
 * already merged into it — into the wrapper's `#parent` slot, which keeps the stacking order intact:
 * default → Twig overrides → native extensions.
 *
 * **Setup channel.** The Options API base is converted into a component whose state runs through
 * `createExtendableSetup()`, which is what makes `swDefineOverride()` apply to it.
 */

import TemplateFactory from 'src/core/factory/template.factory';
import {
    convertOptionsApiBaseToExtendableSetup,
    getOptionsApiBaseConversionBlocker,
} from 'src/app/adapter/options-composition-shim';
import type { ComponentConfig } from 'src/core/factory/async-component.factory';
import { getNativeBlockExtensionTargets, hasNativeSetupExtensionTarget } from './native-extension-targets';
import { analyzeTwigTemplateBlocks } from './twig-template-blocks';

/**
 * Merges the wrapper last so it sits above every plugin override of the same block.
 *
 * `registerTemplateOverride` sorts by index, and no plugin can register a higher one.
 */
const NATIVE_BLOCK_HOST_OVERRIDE_INDEX = Number.MAX_SAFE_INTEGER;

const injectedBlockHosts = new Set<string>();

/**
 * Builds the synthetic Twig override that turns one legacy block into a native extension point.
 *
 * `$dataScope` is the same global accessor migrated templates pass to `<sw-block name>`: on an Options
 * API component it resolves to the instance proxy, and once the base is converted to an extendable setup
 * (the bridge's setup channel) it resolves to the real data scope including override-local state.
 *
 * @example
 * createNativeBlockHostOverride('sw_product_detail_base');
 */
function createNativeBlockHostOverride(blockName: string): string {
    return (
        `{% block ${blockName} %}` +
        `<sw-native-block-host name="${blockName}" :data="$dataScope">` +
        `<template #parent>{% parent %}</template>` +
        `</sw-native-block-host>` +
        `{% endblock %}`
    );
}

/**
 * Represents one entry of `TemplateFactory`'s raw template registry.
 *
 * @example
 * const entry = TemplateFactory.getTemplateRegistry().get('sw-product-detail');
 */
type TwigTemplateRegistryEntry = {
    name: string;
    raw?: string | null;
    extend?: string | null;
    overrides?: { raw?: string | null }[];
};

/**
 * Cheap pre-filter deciding whether a raw template is worth parsing.
 *
 * The bridge runs for every built component, but only a handful of them own a targeted block. A plain
 * substring scan over the raw template rules the rest out before the TwigJS and DOM parses, which is what
 * keeps the bridge off the boot path of a shop whose extensions target a few blocks.
 *
 * A false positive (the name appears in an attribute or a comment) only means the template is parsed;
 * `collectBlockNames` still decides what actually gets a wrapper.
 *
 * @example
 * mentionsTargetedBlock(entry.raw, targetBlockNames);
 */
function mentionsTargetedBlock(rawTemplate: string, targetBlockNames: ReadonlySet<string>): boolean {
    return Array.from(targetBlockNames).some((blockName) => rawTemplate.includes(blockName));
}

/**
 * Registers the wrappers one raw template needs.
 *
 * Both the template and its registered Twig overrides are analyzed for conditional chains: an override
 * may well be the half that pulls a block's content into a chain the base template alone does not show.
 *
 * @example
 * injectNativeBlockHostsForTemplate(entry, targetBlockNames);
 */
function injectNativeBlockHostsForTemplate(entry: TwigTemplateRegistryEntry, targetBlockNames: ReadonlySet<string>): void {
    if (typeof entry.raw !== 'string' || !mentionsTargetedBlock(entry.raw, targetBlockNames)) {
        return;
    }

    const analysis = analyzeTwigTemplateBlocks(entry.name, entry.raw);

    (entry.overrides ?? []).forEach((override) => {
        if (typeof override.raw !== 'string' || override.raw.length === 0) {
            return;
        }

        analyzeTwigTemplateBlocks(entry.name, override.raw).unsafeBlockNames.forEach((blockName) => {
            analysis.unsafeBlockNames.add(blockName);
        });
    });

    analysis.blockNames.forEach((blockName) => {
        if (!targetBlockNames.has(blockName)) {
            return;
        }

        const injectionKey = `${entry.name}:${blockName}`;

        // A template is rendered more than once per session (the factory can mark templates as
        // unresolved and rebuild, and every extending component re-resolves its ancestors), so without
        // this guard the same wrapper would stack and nest.
        if (injectedBlockHosts.has(injectionKey)) {
            return;
        }

        if (analysis.unsafeBlockNames.has(blockName)) {
            injectedBlockHosts.add(injectionKey);
            console.error(
                `[sw-native-block-host] Block "${blockName}" in component "${entry.name}" takes part in a ` +
                    `v-if/v-else chain that crosses its block boundary. Native extensions for this block are ` +
                    `not applied. Migrate the component to <sw-block name="${blockName}"> or move the whole ` +
                    `conditional chain into a single block.`,
            );

            return;
        }

        injectedBlockHosts.add(injectionKey);
        TemplateFactory.registerTemplateOverride(
            entry.name,
            createNativeBlockHostOverride(blockName),
            NATIVE_BLOCK_HOST_OVERRIDE_INDEX,
        );
    });
}

/**
 * @private
 *
 * Registers a `sw-native-block-host` wrapper for every targeted block of one component and its template
 * extension chain.
 *
 * Called once a component's own template and overrides are registered but before the Twig merge renders
 * it - the only window in which a synthetic override can still take part. The extension chain is walked
 * too: a component inherits the blocks of the template it extends, and the merge reads the extended
 * component's *resolved* tokens, so the ancestor needs its wrapper before the child renders.
 *
 * @example
 * injectNativeBlockHostsForComponent('sw-product-detail');
 */
export function injectNativeBlockHostsForComponent(componentName: string): void {
    const targetBlockNames = getNativeBlockExtensionTargets();

    if (targetBlockNames.size === 0) {
        return;
    }

    const templateRegistry = TemplateFactory.getTemplateRegistry() as Map<string, TwigTemplateRegistryEntry>;
    const visited = new Set<string>();
    let entry = templateRegistry.get(componentName);

    while (entry && !visited.has(entry.name)) {
        visited.add(entry.name);
        injectNativeBlockHostsForTemplate(entry, targetBlockNames);
        entry = entry.extend ? templateRegistry.get(entry.extend) : undefined;
    }
}

/**
 * @private
 *
 * Registers the wrappers for every template known to the registry.
 *
 * Components are built lazily, so this pass only sees the templates registered up front; the per
 * component pass above covers the rest. Running both is safe - the injection key guards against
 * registering a wrapper twice.
 *
 * @example
 * injectNativeBlockHosts();
 */
export function injectNativeBlockHosts(): void {
    const targetBlockNames = getNativeBlockExtensionTargets();

    if (targetBlockNames.size === 0) {
        return;
    }

    const templateRegistry = TemplateFactory.getTemplateRegistry() as Map<string, TwigTemplateRegistryEntry>;

    Array.from(templateRegistry.values()).forEach((entry) => {
        injectNativeBlockHostsForTemplate(entry, targetBlockNames);
    });
}

/**
 * @private
 *
 * Clears the record of already-injected wrappers. Test teardown only.
 *
 * @example
 * resetNativeBlockHosts();
 */
export function resetNativeBlockHosts(): void {
    injectedBlockHosts.clear();
}

/**
 * @private
 *
 * Makes an Options API base component extendable when a native `.override.vue` registers setup state for
 * it.
 *
 * This is the bridge's second channel: the template channel (above) gives a native extension its
 * rendering position, this one gives it the base's setup state - the point at which `swDefineOverride()`,
 * `useSwPreviousState()` and override-local `__swOverride` bindings start working on a component that was
 * never migrated. Once the base runs through `createExtendableSetup()`, `$dataScope` also resolves to the
 * real data scope, so the injected `sw-native-block-host` picks it up without any extra wiring.
 *
 * Returns the config unchanged whenever the conversion does not apply or fails: a plugin override must
 * never take a core screen down with it.
 *
 * @example
 * config = bridgeNativeSetupExtensions('sw-product-detail', config);
 */
export function bridgeNativeSetupExtensions(componentName: string, config: ComponentConfig): ComponentConfig {
    if (!hasNativeSetupExtensionTarget(componentName)) {
        return config;
    }

    // Already a Composition API component: either a native setup SFC, which is extendable by
    // construction, or a hand-written setup() the conversion has nothing to say about.
    if (typeof config.setup === 'function') {
        return config;
    }

    const blocker = getOptionsApiBaseConversionBlocker(config);

    if (blocker) {
        console.error(
            `[sw-native-setup-bridge] Component "${componentName}" cannot be made extendable because ${blocker}. ` +
                `Native setup extensions for this component are not applied. Template extensions ` +
                `(<sw-block extends="...">) are unaffected.`,
        );

        return config;
    }

    try {
        return convertOptionsApiBaseToExtendableSetup(componentName, config);
    } catch (error) {
        console.error(
            `[sw-native-setup-bridge] Converting component "${componentName}" into an extendable setup ` +
                `component failed. The unconverted component is used instead, so native setup extensions ` +
                `for it are not applied.`,
            error,
        );

        return config;
    }
}
