import { defineAsyncComponent, getCurrentInstance, type ComponentInternalInstance, type RenderFunction } from 'vue';
import { getScriptSetupDataScope } from '../composition-extension-system/data-scope-helper';
import { createThisProxy } from './instance';
/** @sw-package framework */
import type { ComponentConfig, IndexedAwaitedComponentConfig } from 'src/core/factory/async-component.factory';
import { inheritanceOrder } from './merge-options';

type Definition = ComponentConfig & {
    __swLegacyRegistrations?: IndexedAwaitedComponentConfig[];
    __swLegacyNames?: string[];
    __swLegacyRender?: RenderFunction;
};

/**
 * Merge options Vue must know before setup: props, events, local registrations and the render function.
 * Stateful Options API hooks stay out of Vue's extends chain; the shim executes them exactly once.
 * @private
 */
export function prepareLegacyComponent(
    name: string,
    base: ComponentConfig,
    registrations: IndexedAwaitedComponentConfig[],
): ComponentConfig {
    const definition: Definition = { ...base, name, __swExtendable: true };
    const layers = [
        ...new Set([
            ...((base as Definition).__swLegacyRegistrations ?? []),
            ...registrations,
        ]),
    ];
    definition.__swLegacyRegistrations = layers;
    definition.__swLegacyNames = [
        ...new Set([
            ...((base as Definition).__swLegacyNames ?? []),
            name,
        ]),
    ];
    for (const registration of registrations) {
        const config = registration.resolvedConfig;
        if (!config) throw new Error(`[Options API Shim] Component "${name}" was prepared before its overrides resolved.`);
        for (const options of inheritanceOrder(config)) mergeDefinitionOptions(definition, options);
    }
    if (definition.__swLegacyRender) {
        const setup = base.setup;
        const render = definition.__swLegacyRender;
        definition.setup = function (props, context) {
            const instance = getCurrentInstance();
            const result: unknown = setup?.(props, context);
            if (result instanceof Promise) {
                return result.then(() => () => renderLegacyContent(render, instance));
            }
            return () => renderLegacyContent(render, instance);
        };
    }
    return definition;
}

function normalizeNames(value: unknown): Record<string, unknown> {
    if (Array.isArray(value))
        return Object.fromEntries(
            value.map((name: string) => [
                name,
                null,
            ]),
        );
    return (value ?? {}) as Record<string, unknown>;
}

function mergeDefinitionOptions(definition: Definition, options: ComponentConfig): void {
    for (const key of [
        'props',
        'emits',
    ] as const) {
        if (options[key])
            Object.assign(definition, { [key]: { ...normalizeNames(definition[key]), ...normalizeNames(options[key]) } });
    }
    definition.components = { ...definition.components, ...options.components };
    definition.directives = { ...definition.directives, ...options.directives };
    if (options.inheritAttrs !== undefined) definition.inheritAttrs = options.inheritAttrs;
    if (options.render) definition.__swLegacyRender = options.render as RenderFunction;
}

function renderLegacyContent(render: RenderFunction, instance: ComponentInternalInstance | null) {
    const state = instance ? (getScriptSetupDataScope(instance) ?? {}) : {};
    const proxy = createThisProxy(state, instance?.props ?? {}, {}, {}, { instance, state });
    return render.call(proxy);
}

/**
 * Direct SFC imports await legacy registrations before Vue normalizes props and events.
 * Factory consumers resolve the same definition before building inheritance, avoiding two Options executions.
 * @private
 */
export function createLegacyComponent<T extends ComponentConfig>(base: T, name: string): T {
    const resolve = async () => {
        const registrations = Shopware.Component.getOverrideRegistry().get(name) ?? [];
        await Promise.all(registrations.map((registration) => registration.config()));
        return prepareLegacyComponent(name, base, registrations);
    };
    return Object.assign(defineAsyncComponent(resolve), {
        name,
        _renderedBySfcTemplate: true,
        __swResolveComponent: resolve,
    }) as unknown as T;
}
