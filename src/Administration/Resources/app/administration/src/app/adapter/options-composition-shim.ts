/**
 * @sw-package framework
 *
 * Options API to Composition API Override Shim
 *
 * This module provides a compatibility layer that allows Options API component overrides
 * to work transparently when the target component uses Composition API with createExtendableSetup().
 *
 * @experimental stableVersion:v6.8.0 feature:ADMIN_COMPOSITION_API_EXTENSION_SYSTEM
 */

import type { ComponentConfig } from 'src/core/factory/async-component.factory';
import type { ComponentState, OverrideFn, ExtendedComponentConfig } from './options-composition-shim/types';
import { mergeMixins } from './options-composition-shim/merge-options';
import { createThisProxy } from './options-composition-shim/instance';
import { convertData, convertComputed, convertMethods } from './options-composition-shim/state';
import { LIFECYCLE_HOOKS, resolveInject, setupWatchers, setupLifecycleHooks } from './options-composition-shim/effects';

/** @private */
export type { OverrideFn } from './options-composition-shim/types';

const OPTION_KEYS = [
    'data',
    'methods',
    'computed',
    'watch',
    'mixins',
    'inject',
    'extends',
] as const;

/** @private */
export function shouldActivateShim(overrideConfig: ComponentConfig): boolean {
    const extended = overrideConfig as ExtendedComponentConfig;
    const hasOptionKeys = OPTION_KEYS.some((key) => {
        const val: unknown = extended[key];
        return Array.isArray(val) ? val.length > 0 : !!val;
    });
    const hasLifecycleHooks = LIFECYCLE_HOOKS.some((hook) => !!extended[hook]);

    return hasOptionKeys || hasLifecycleHooks;
}

/** @private */
export function convertOptionsApiOverrideToCompositionApi<
    COMPONENT_NAME extends keyof ComponentPublicApiMapping & string = string,
>(componentName: COMPONENT_NAME, optionsConfig: ComponentConfig): OverrideFn {
    logDeprecationWarning(componentName);
    checkUnsupportedFeatures(componentName, optionsConfig);

    return (previousState: ComponentState, props: ComponentState): ComponentState => {
        const result: ComponentState<COMPONENT_NAME> = {} as ComponentState<COMPONENT_NAME>;

        const mergedConfig = mergeMixins(optionsConfig);

        if (mergedConfig.data) {
            Object.assign(result, convertData(mergedConfig.data));
        }

        // Resolve inject values from Vue's provide/inject system.
        // This must run while we are still inside the component's setup() context
        // (the immediate watch in createExtendableSetup guarantees this).
        const injectedValues = resolveInject(mergedConfig.inject);

        // Create the this proxy (needs to be created after data but before computed/methods)
        const thisProxy = createThisProxy(previousState, props, result, injectedValues);

        if (mergedConfig.computed) {
            Object.assign(result, convertComputed(mergedConfig.computed, thisProxy));
        }

        if (mergedConfig.methods) {
            Object.assign(result, convertMethods(mergedConfig.methods, thisProxy));
        }

        if (mergedConfig.watch) {
            setupWatchers(mergedConfig.watch, thisProxy);
        }

        if (mergedConfig._lifecycleHooks) {
            setupLifecycleHooks(mergedConfig._lifecycleHooks, thisProxy);
        }

        return result;
    };
}

const UNSUPPORTED_OPTIONS = [
    'components',
    'directives',
    'provide',
    'template',
    'extends',
    'inheritAttrs',
    'emits',
] as const;

function checkUnsupportedFeatures(componentName: string, config: ComponentConfig): void {
    if (config.render && typeof config.render === 'function') {
        console.error(
            `[Options API Shim] Custom render() functions are not supported by the compatibility shim. ` +
                `Component "${componentName}" will not work correctly. ` +
                `Please migrate to Composition API.`,
        );
    }

    const extended = config as ExtendedComponentConfig;
    UNSUPPORTED_OPTIONS.forEach((key) => {
        if (extended[key]) {
            console.warn(
                `[Options API Shim] "${key}" is not supported by the compatibility shim ` +
                    `in component "${componentName}". This option will be ignored.`,
            );
        }
    });
}

function logDeprecationWarning(componentName: string): void {
    console.warn(
        `[Deprecation Warning] Component "${componentName}" is being overridden with Options API patterns, ` +
            `but the target uses Composition API. A compatibility shim has been activated. ` +
            `This is a temporary solution and may have limitations. ` +
            `Please migrate your override to use Shopware.Component.overrideComponentSetup(). ` +
            `See: https://developer.shopware.com/docs/resources/references/core-reference/administration-reference/composition-api`,
    );
}
