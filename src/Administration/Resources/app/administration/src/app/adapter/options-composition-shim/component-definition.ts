/** @sw-package framework */
import { defineAsyncComponent, unref, type ComponentInternalInstance } from 'vue';
import type { ComponentConfig, IndexedAwaitedComponentConfig } from 'src/core/factory/async-component.factory';
import { baseOptionsData, initializeNativeOptions, readOriginalBinding } from './native-options-state';
import { nativeOptionsChain } from './native-options-chain';

type Definition = ComponentConfig & {
    __swNativeOptions?: boolean;
    __swLegacyBase?: ComponentConfig;
    __swLegacyRegistrations?: IndexedAwaitedComponentConfig[];
    __swLegacyNames?: string[];
};

/** Prepare a normal Vue Options chain around the SFC. Vue owns all Options initialization. @private */
export function prepareLegacyComponent(
    name: string,
    base: ComponentConfig,
    registrations: IndexedAwaitedComponentConfig[],
): ComponentConfig {
    const previous = base as Definition;
    const original = previous.__swLegacyBase ?? base;
    const layers = [
        ...new Set([
            ...(previous.__swLegacyRegistrations ?? []),
            ...registrations,
        ]),
    ];
    const configs = layers.map((registration) => {
        if (!registration.resolvedConfig)
            throw new Error(`[Options API Bridge] Component "${name}" was prepared before its overrides resolved.`);
        return registration.resolvedConfig;
    });
    const mixins = nativeOptionsChain(configs);
    const foundation = createBaseOptions(original);
    const definition: Definition = {
        name,
        __hmrId: original.__hmrId,
        __swExtendable: true,
        __swNativeOptions: true,
        __swLegacyBase: original,
        __swLegacyRegistrations: layers,
        __swLegacyNames: [
            ...new Set([
                ...(previous.__swLegacyNames ?? []),
                name,
            ]),
        ],
        legacyOptionsBindings: original.legacyOptionsBindings,
        extends: foundation,
        mixins,
        setup: original.setup,
    };
    retainRootExposure(definition, mixins.length ? mixins : [foundation]);
    retainLegacyRender(definition, original, mixins);
    exposeRouteGuards(definition, [
        foundation,
        ...mixins,
    ]);
    return definition;
}

/** Shopware hoists the last declared override option; Vue only accepts expose on the final root. */
function retainRootExposure(definition: Definition, configs: ComponentConfig[]): void {
    for (let index = configs.length - 1; index >= 0; index -= 1) {
        const config = configs[index];
        if (!Object.hasOwn(config, 'expose')) continue;
        definition.expose = config.expose;
        delete config.expose;
        return;
    }
}

/** Keep base state and its original Options declarations in one ancestor. */
function createBaseOptions(original: ComponentConfig): ComponentConfig {
    const originalData = original.data;
    return {
        ...original,
        ...baseMemberOptions(original),
        mixins: [
            {
                beforeCreate(this: { $: ComponentInternalInstance }) {
                    initializeNativeOptions(this.$);
                },
            },
            ...((original.mixins ?? []) as ComponentConfig[]),
        ],
        data(this: { $: ComponentInternalInstance }, vm: object) {
            return {
                ...baseOptionsData(this.$),
                ...(originalData as ((this: object, vm: object) => object) | undefined)?.call(this, vm),
            };
        },
    };
}

function retainLegacyRender(definition: Definition, original: ComponentConfig, mixins: ComponentConfig[]): void {
    // Vue cannot replace a render function returned by setup with an Options render declaration.
    const customRender = lastDefinitionOption(mixins, 'render') as ComponentConfig['render'];
    definition.render = customRender ?? original.render;
    if (customRender && original.setup) {
        const setup = original.setup;
        definition.setup = function (props, context) {
            const result: unknown = setup(props, context);
            const stateOnly = (state: unknown) => (typeof state === 'function' ? undefined : state);
            return result instanceof Promise ? result.then(stateOnly) : stateOnly(result);
        } as ComponentConfig['setup'];
    }
}

function exposeRouteGuards(definition: Definition, configs: ComponentConfig[]): void {
    for (const guard of [
        'beforeRouteEnter',
        'beforeRouteUpdate',
        'beforeRouteLeave',
    ] as const) {
        const handler = lastDefinitionOption(configs, guard);
        if (handler) definition[guard] = handler;
    }
}

/** Preserve the original Options categories for $data and $options consumers. */
type BaseMember = (this: { $: ComponentInternalInstance }, ...args: unknown[]) => unknown;
type BaseComputed = BaseMember | { get: BaseMember; set?: BaseMember };

function baseMemberOptions(base: ComponentConfig) {
    const methods = { ...(base.methods as Record<string, BaseMember> | undefined) };
    const computed = { ...(base.computed as Record<string, BaseComputed> | undefined) };
    const members = (base.legacyOptionsMembers ?? {}) as Record<string, string>;
    for (const [
        name,
        kind,
    ] of Object.entries(members)) {
        if (kind === 'method')
            methods[name] = function (this: { $: ComponentInternalInstance }, ...args: unknown[]) {
                const binding = readOriginalBinding(this.$, name) as (...values: unknown[]) => unknown;
                return binding.apply(this, args);
            };
        if (kind === 'computed')
            computed[name] = function () {
                return unref(readOriginalBinding(this.$, name));
            };
        if (kind === 'writable-computed')
            computed[name] = {
                get(this: { $: ComponentInternalInstance }) {
                    return unref(readOriginalBinding(this.$, name));
                },
                set(this: { $: ComponentInternalInstance }, value: unknown) {
                    const binding = readOriginalBinding(this.$, name) as { value: unknown };
                    binding.value = value;
                },
            };
    }
    return { methods, computed };
}

/** Vue Router reads component definitions before an instance exists. Its last declaration wins. */
function lastDefinitionOption(configs: ComponentConfig[], key: string): unknown {
    let value: unknown;
    for (const config of configs) {
        const parent = typeof config.extends === 'object' ? lastDefinitionOption([config.extends], key) : undefined;
        value = config[key] ?? lastDefinitionOption((config.mixins ?? []) as ComponentConfig[], key) ?? parent ?? value;
    }
    return value;
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

/** Preserve legacy definition options when Vite passes an updated SFC module to Vue HMR. @private */
export async function resolveLegacyHotUpdate<T extends { default?: ComponentConfig }>(module: T): Promise<T> {
    const resolve = module.default?.__swResolveComponent;
    return resolve ? { ...module, default: await resolve() } : module;
}
