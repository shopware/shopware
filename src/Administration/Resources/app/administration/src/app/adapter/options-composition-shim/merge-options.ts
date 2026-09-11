/**
 * @sw-package framework
 * @private
 */

import type { ComponentConfig } from 'src/core/factory/async-component.factory';
import type {
    InjectConfig,
    MergedConfig,
    LifecycleHookName,
    LifecycleHookFn,
    AnyFn,
    ComputedDefinition,
    WatchDefinition,
    ExtendedComponentConfig,
} from './types';
import { LIFECYCLE_HOOKS } from './effects';

function flattenMixins(mixin: ComponentConfig): ComponentConfig[] {
    const nested = mixin.mixins ? mixin.mixins.flatMap((m) => flattenMixins(m as ComponentConfig)) : [];
    return [
        ...nested,
        mixin,
    ];
}

function mergeInjectConfigs(existing: InjectConfig, incoming: InjectConfig): InjectConfig {
    const normalized: Record<string, unknown> = {};

    if (Array.isArray(existing)) {
        existing.forEach((key: string) => {
            normalized[key] = key;
        });
    } else if (existing && typeof existing === 'object') {
        Object.assign(normalized, existing);
    }

    if (Array.isArray(incoming)) {
        incoming.forEach((key: string) => {
            if (!Object.hasOwn(normalized, key)) {
                normalized[key] = key;
            }
        });
    } else if (incoming && typeof incoming === 'object') {
        const incomingObj = incoming as Record<string, unknown>;
        Object.entries(incomingObj).forEach(
            ([
                key,
                val,
            ]) => {
                if (!Object.hasOwn(normalized, key)) {
                    normalized[key] = val;
                }
            },
        );
    }

    return normalized as InjectConfig;
}

/** @private */
export function mergeMixins(config: ComponentConfig): MergedConfig {
    const lifecycleHooks: Partial<Record<LifecycleHookName, LifecycleHookFn[]>> = {};
    // Collect data factories in merge order so each is called exactly once.
    // Mixin factories are pushed first (deepest ancestor first via flattenMixins),
    // then the component's own factory last — so component keys win on conflict.
    const allDataFns: Array<() => Record<string, unknown>> = [];

    // Vue's ComponentOptions types methods/computed/watch as `any` internally,
    // so we cast once here at the boundary and let MergedConfig carry the correct types.
    const merged: MergedConfig = {
        methods: { ...(config.methods as Record<string, AnyFn>) },
        computed: { ...(config.computed as Record<string, ComputedDefinition>) },
        watch: { ...(config.watch as Record<string, WatchDefinition>) },
        inject: config.inject,
    };

    if (config.mixins && config.mixins.length > 0) {
        const allMixins = config.mixins.flatMap((m) => flattenMixins(m as ComponentConfig));
        allMixins.forEach((mixin: ComponentConfig) => {
            const extendedMixin = mixin as ExtendedComponentConfig;

            // Collect lifecycle hooks from mixin (mixin hooks fire before component hooks)
            LIFECYCLE_HOOKS.forEach((hook) => {
                const hookFn = extendedMixin[hook];
                if (hookFn) {
                    if (!lifecycleHooks[hook]) {
                        lifecycleHooks[hook] = [];
                    }
                    lifecycleHooks[hook].push(hookFn);
                }
            });

            // Collect the mixin's data factory without calling it yet
            if (mixin.data) {
                const mixinData = mixin.data;
                allDataFns.push(
                    typeof mixinData === 'function'
                        ? () => (mixinData as unknown as () => Record<string, unknown>)()
                        : () => mixinData as unknown as Record<string, unknown>,
                );
            }

            if (mixin.methods) {
                merged.methods = { ...(mixin.methods as Record<string, AnyFn>), ...merged.methods };
            }

            if (mixin.computed) {
                merged.computed = { ...(mixin.computed as Record<string, ComputedDefinition>), ...merged.computed };
            }

            if (mixin.watch) {
                merged.watch = { ...(mixin.watch as Record<string, WatchDefinition>), ...merged.watch };
            }

            if (mixin.inject) {
                merged.inject = mergeInjectConfigs(merged.inject, mixin.inject);
            }
        });
    }

    // Add the component's own data factory last so its keys win over mixin keys
    if (config.data) {
        const configData = config.data;
        allDataFns.push(
            typeof configData === 'function'
                ? () => (configData as unknown as () => Record<string, unknown>)()
                : () => configData as unknown as Record<string, unknown>,
        );
    }

    // Produce a single merged factory that calls each original factory exactly once
    if (allDataFns.length > 0) {
        merged.data = () => allDataFns.reduce<Record<string, unknown>>((acc, fn) => ({ ...acc, ...fn() }), {});
    }

    // Component's own hooks go last (after mixin hooks), matching Vue's merge strategy
    const extendedConfig = config as ExtendedComponentConfig;
    LIFECYCLE_HOOKS.forEach((hook) => {
        const hookFn = extendedConfig[hook];
        if (hookFn) {
            if (!lifecycleHooks[hook]) {
                lifecycleHooks[hook] = [];
            }
            lifecycleHooks[hook].push(hookFn);
        }
    });

    if (Object.keys(lifecycleHooks).length > 0) {
        merged._lifecycleHooks = lifecycleHooks;
    }

    return merged;
}
