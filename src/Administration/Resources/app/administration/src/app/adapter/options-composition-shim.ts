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

import { getCurrentInstance, shallowRef, isRef } from 'vue';
import type { ComponentConfig } from 'src/core/factory/async-component.factory';
import type {
    ComponentState,
    OverrideFn,
    ExtendedComponentConfig,
    LegacyOverrideOwner,
} from './options-composition-shim/types';
import { createLegacyBindingView } from './options-composition-shim/legacy-bindings';
import { mergeMixins } from './options-composition-shim/merge-options';
import { createThisProxy } from './options-composition-shim/instance';
import { convertData, convertComputed, convertMethods } from './options-composition-shim/state';
import {
    LIFECYCLE_HOOKS,
    resolveInject,
    setupWatchers,
    setupLifecycleHooks,
    setupProvide,
} from './options-composition-shim/effects';

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
    'provide',
    'props',
    'emits',
    'components',
    'directives',
    'inheritAttrs',
    'render',
] as const;

/** @private */
export function shouldActivateShim(overrideConfig: ComponentConfig): boolean {
    const extended = overrideConfig as ExtendedComponentConfig;
    const hasOptionKeys = OPTION_KEYS.some((key) => {
        const val: unknown = extended[key];
        return Array.isArray(val) ? val.length > 0 : val !== undefined && val !== null;
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

    const mergedConfig = mergeMixins(optionsConfig);

    return (
        previousState: ComponentState,
        props: ComponentState,
        _context?: unknown,
        owner?: LegacyOverrideOwner,
    ): ComponentState => {
        const result: ComponentState = {};
        const instance = owner?.instance ?? getCurrentInstance();
        const injected: ComponentState = {};
        if (owner?.options) {
            const options = owner.options;
            Object.assign(options, mergedConfig, {
                methods: { ...options.methods, ...mergedConfig.methods } as Record<string, (...args: unknown[]) => unknown>,
                computed: { ...options.computed, ...mergedConfig.computed } as NonNullable<typeof mergedConfig.computed>,
            });
        }
        const {
            aliases,
            previous: legacyPrevious,
            owner: legacyOwner,
        } = createLegacyBindingView(previousState, owner, instance);
        const thisProxy = createThisProxy(legacyPrevious, props, result, injected, legacyOwner);
        const { beforeCreate, ...remainingHooks } = mergedConfig._lifecycleHooks ?? {};

        setupLifecycleHooks({ beforeCreate }, thisProxy, instance);
        Object.assign(injected, resolveInject(mergedConfig.inject, instance, thisProxy));
        for (const [
            key,
            value,
        ] of Object.entries(injected))
            result[key] = isRef(value) ? value : shallowRef(value);
        Object.assign(result, convertMethods(mergedConfig.methods ?? {}, thisProxy));
        if (mergedConfig.data) {
            const data = convertData(mergedConfig.data, thisProxy);
            Object.assign(result, data);
            if (owner?.data) Object.assign(owner.data, data);
        }
        Object.assign(result, convertComputed(mergedConfig.computed ?? {}, thisProxy));
        setupWatchers(mergedConfig.watch ?? {}, thisProxy);
        setupProvide(mergedConfig.provide, thisProxy, instance);
        setupLifecycleHooks(remainingHooks, thisProxy, instance);

        // Instance fields created by hooks (for example timer handles) need the same state bridge as data().
        for (const [
            key,
            value,
        ] of Object.entries(result)) {
            if (!isRef(value) && typeof value !== 'function') result[key] = shallowRef(value);
        }
        for (const [
            legacyName,
            binding,
        ] of Object.entries(aliases)) {
            if (legacyName !== binding && Object.hasOwn(result, legacyName)) {
                result[binding] = result[legacyName] as unknown;
                delete result[legacyName];
            }
        }
        return result;
    };
}

function checkUnsupportedFeatures(componentName: string, config: ComponentConfig): void {
    if (config.template) {
        console.warn(
            `[Options API Shim] A raw "template" is not supported by the compatibility shim in component "${componentName}". Register Twig templates through Shopware.Component.override().`,
        );
    }
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
