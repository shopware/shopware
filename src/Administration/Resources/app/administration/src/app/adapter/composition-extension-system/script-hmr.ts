/**
 * @sw-package framework
 * @private
 */
import { getCurrentInstance, nextTick, onUnmounted } from 'vue';
import type { ComponentInternalInstance, ComponentOptions } from 'vue';
import type { OverrideFn } from '../options-composition-shim';
import type { ComponentConfig, SetupHmrMetadata } from 'src/core/factory/async-component.factory';

// Vue's HMR runtime invalidates these caches for mounted instances; unmounted factory copies need
// the same invalidation. The installed runtime exposes them but omits them from its public types.
type HmrAppContext = ComponentInternalInstance['appContext'] & {
    propsCache: WeakMap<object, unknown>;
    emitsCache: WeakMap<object, unknown>;
    optionsCache: WeakMap<object, unknown>;
};
type HmrComponent = ComponentOptions & {
    __hmrId?: string;
    __shopwareSetupHmr?: SetupHmrMetadata;
};
type HmrRuntime = {
    createRecord: (id: string, component: HmrComponent) => boolean;
    reload: (id: string, component: HmrComponent) => void;
    rerender: (id: string) => void;
};
type Registration = { callback: OverrideFn; metadata: SetupHmrMetadata };

const definitionContexts = new WeakMap<object, Set<HmrAppContext>>();
const nativeDefinitions = new WeakMap<object, Set<ComponentConfig>>();
const registrations = new Map<string, Map<string, Registration>>();
const mountedComponents = new Map<string, Map<HmrComponent, number>>();
const pendingUpdates = new Map<string, SetupHmrMetadata>();

function runtime(): HmrRuntime | undefined {
    return (globalThis as typeof globalThis & { __VUE_HMR_RUNTIME__?: HmrRuntime }).__VUE_HMR_RUNTIME__;
}

function enabled(): boolean {
    return process.env.NODE_ENV !== 'production' && Shopware.Feature.isActive('ADMIN_VITE_8');
}

/**
 * The factory caches copies of SFC definitions. Vue only updates mounted copies itself, so retain
 * the cached definitions under the module's hot data for updates while a component is unmounted.
 * @private
 */
export function trackNativeHmrDefinition(config: ComponentConfig): void {
    const metadata = config.__shopwareSetupHmr;
    const data = metadata?.data;
    if (metadata?.mode !== 'base' || !data || data.reloadPage) return;
    const definitions = nativeDefinitions.get(data) ?? new Set<ComponentConfig>();
    nativeDefinitions.set(data, definitions);
    definitions.add(config);
    data.updateDefinitions = (component) => {
        for (const definition of definitions) {
            const name = definition.name;
            Object.keys(definition).forEach((key) => {
                if (!(key in component) && key !== 'name') Reflect.deleteProperty(definition, key);
            });
            Object.assign(definition, component);
            if (name) definition.name = name;
            for (const context of definitionContexts.get(definition) ?? []) {
                context.propsCache.delete(definition);
                context.emitsCache.delete(definition);
                context.optionsCache.delete(definition);
            }
        }
    };
}

function rememberParent(): void {
    const instance = getCurrentInstance();
    const metadata = (instance?.type as HmrComponent | undefined)?.__shopwareSetupHmr;
    const parent = instance?.parent?.type as HmrComponent | undefined;
    const data = metadata?.data;
    if (data && parent?.__hmrId) {
        const id = parent.__hmrId;
        // Vue's reload queues the parent, but v-once can reuse its cached vnode. Rerender clears that
        // cache through the HMR API without remounting the parent or resetting its local state.
        data.refreshParent = () => runtime()?.rerender(id);
    }
}

/** @private */
export function trackSetupHmrComponent(componentName: string): void {
    if (!enabled()) return;
    rememberParent();
    const instance = getCurrentInstance();
    const component = instance?.type as HmrComponent | undefined;
    if (!component?.__hmrId || !instance) return;
    if (component.__shopwareSetupHmr?.mode === 'base') {
        const contexts = definitionContexts.get(component) ?? new Set<HmrAppContext>();
        contexts.add(instance.appContext as HmrAppContext);
        definitionContexts.set(component, contexts);
    }

    const components = mountedComponents.get(componentName) ?? new Map<HmrComponent, number>();
    mountedComponents.set(componentName, components);
    components.set(component, (components.get(component) ?? 0) + 1);
    runtime()?.createRecord(component.__hmrId, component);
    onUnmounted(() => {
        const count = (components.get(component) ?? 1) - 1;
        if (count > 0) components.set(component, count);
        else components.delete(component);
        if (components.size === 0 && mountedComponents.get(componentName) === components) {
            mountedComponents.delete(componentName);
        }
    });
}

/** @private */
export function invalidateFailedSetupHmr(componentName: string): void {
    const pending = pendingUpdates.get(componentName);
    if (!pending) return;
    pendingUpdates.delete(componentName);
    pending.invalidate();
}

function remountComponents(componentName: string, hmr: HmrRuntime): void {
    const reloaded = new Set<string>();
    for (const definition of mountedComponents.get(componentName)?.keys() ?? []) {
        if (definition.__hmrId && !reloaded.has(definition.__hmrId)) {
            reloaded.add(definition.__hmrId);
            hmr.reload(definition.__hmrId, { ...definition });
        }
    }
}

function currentOverride(componentName: string): { owner: string; metadata: SetupHmrMetadata } | undefined {
    const component = getCurrentInstance()?.type as HmrComponent | undefined;
    const metadata = component?.__shopwareSetupHmr;
    const owner = component?.__hmrId;
    if (!owner || metadata?.mode !== 'override' || metadata.componentName !== componentName) return undefined;

    return { owner, metadata };
}

/**
 * Replaces an owned callback without notifying watchers bound to the previous chain.
 * Those instances must unmount before any changed callback runs against fresh base state.
 * @private
 */
export function registerSetupOverrideForHmr(
    componentName: string,
    callback: OverrideFn,
    overrides: Record<string, OverrideFn[]>,
): boolean {
    if (!enabled()) return false;
    const registration = currentOverride(componentName);
    if (!registration) return false;
    const { owner, metadata } = registration;
    rememberParent();

    const owners = registrations.get(componentName) ?? new Map<string, Registration>();
    registrations.set(componentName, owners);
    const previous = owners.get(owner);
    const chain = overrides[componentName] ?? [];
    const index = previous ? chain.indexOf(previous.callback) : -1;
    if (index < 0) {
        owners.set(owner, { callback, metadata });
        overrides[componentName] = [...chain, callback];
        return true;
    }
    // A template rerender can recreate the hidden registration component without changing its script.
    if (previous?.metadata.version === metadata.version) return true;
    const hmr = runtime();
    if (!hmr) {
        metadata.invalidate();
        return true;
    }

    const replacement = [...chain];
    replacement[index] = callback;
    owners.set(owner, { callback, metadata });
    overrides[componentName] = replacement;
    pendingUpdates.set(componentName, metadata);
    remountComponents(componentName, hmr);
    void nextTick(() => {
        if (pendingUpdates.get(componentName) === metadata) pendingUpdates.delete(componentName);
    });
    return true;
}
