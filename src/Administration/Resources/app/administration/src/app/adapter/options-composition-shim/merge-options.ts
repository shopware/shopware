/**
 * @sw-package framework
 * @private
 *
 * Normalizes the Options inheritance tree once, in Vue's ancestor-first order.
 * Scalar definitions use the last declaration; watchers and lifecycle hooks accumulate.
 */
import type { ComponentConfig } from 'src/core/factory/async-component.factory';
import type { AnyFn, MergedConfig, WatchDefinition } from './types';
import { LIFECYCLE_HOOKS } from './effects';

/** @private */
export function mergeMixins(config: ComponentConfig): MergedConfig {
    const merged: MergedConfig = { methods: {}, computed: {}, watch: {}, inject: {}, _lifecycleHooks: {} };
    const dataFactories: NonNullable<MergedConfig['data']>[] = [];
    const providers: NonNullable<MergedConfig['provide']>[] = [];

    for (const options of inheritanceOrder(config)) {
        Object.assign(merged.methods!, options.methods);
        Object.assign(merged.computed!, options.computed);
        Object.assign(merged.inject!, normalizeInject(options.inject));

        if (options.data) dataFactories.push(options.data as NonNullable<MergedConfig['data']>);
        if (options.provide) providers.push(options.provide as NonNullable<MergedConfig['provide']>);

        for (const [
            name,
            handler,
        ] of Object.entries(options.watch ?? {})) {
            merged.watch![name] = [
                ...new Set([
                    ...asArray(merged.watch![name]),
                    ...asArray(handler as WatchDefinition),
                ]),
            ];
        }

        for (const hook of LIFECYCLE_HOOKS) {
            const handlers = options[hook] as AnyFn | AnyFn[] | undefined;
            merged._lifecycleHooks![hook] = [
                ...new Set([
                    ...(merged._lifecycleHooks![hook] ?? []),
                    ...asArray(handlers),
                ]),
            ];
        }
    }

    if (dataFactories.length) {
        merged.data = function (vm) {
            return Object.assign(
                {} as Record<string, unknown>,
                ...dataFactories.map((factory) => factory.call(this, vm)),
            ) as Record<string, unknown>;
        };
    }
    if (providers.length) {
        merged.provide = function () {
            return Object.assign(
                {} as Record<PropertyKey, unknown>,
                ...providers.map((provider) => (typeof provider === 'function' ? provider.call(this) : provider)),
            ) as Record<PropertyKey, unknown>;
        };
    }
    return merged;
}

/** @private */
export function inheritanceOrder(config: ComponentConfig, ancestors = new Set<ComponentConfig>()): ComponentConfig[] {
    if (ancestors.has(config)) throw new Error('[Options API Shim] Circular mixin or extends configuration.');
    const nextAncestors = new Set(ancestors).add(config);
    const parent = typeof config.extends === 'object' && config.extends ? config.extends : null;
    return [
        ...(parent ? inheritanceOrder(parent, nextAncestors) : []),
        ...(config.mixins ?? []).flatMap((mixin) => inheritanceOrder(mixin as ComponentConfig, nextAncestors)),
        config,
    ];
}

function normalizeInject(config: ComponentConfig['inject']): Record<string, unknown> {
    return Array.isArray(config)
        ? Object.fromEntries(
              config.map((name: string) => [
                  name,
                  name,
              ]),
          )
        : ((config as Record<string, unknown>) ?? {});
}

function asArray<T>(value: T | T[] | undefined): T[] {
    return value === undefined ? [] : Array.isArray(value) ? value : [value];
}
