/**
 * @sw-package framework
 * @private
 *
 * Hot updates the scripts of native setup SFCs while the Vite 8 watcher runs.
 * The generated accept handler of each SFC reloads it through Vue's HMR runtime,
 * this module keeps override callbacks and the components they change in step with that.
 */
import { getCurrentInstance, onUnmounted } from 'vue';
import type { ComponentInternalInstance, ComponentOptions } from 'vue';
import type { OverrideFn } from '../options-composition-shim';
import type { SetupHmrMetadata } from 'src/core/factory/async-component.factory';

type HmrComponent = ComponentOptions & {
    __hmrId?: string;
    __shopwareSetupHmr?: SetupHmrMetadata;
};

type HmrRuntime = {
    reload: (id: string, component: HmrComponent) => void;
};

// Overrides target a component name, while Vue's HMR runtime knows components only by their id.
const mountedInstances = new Map<string, Set<ComponentInternalInstance>>();
// Keyed by the HMR id of the override SFC, so an edit replaces its callback instead of adding another one.
const registrations = new Map<string, { callback: OverrideFn; version: string }>();

function hmrRuntime(): HmrRuntime | undefined {
    return (globalThis as typeof globalThis & { __VUE_HMR_RUNTIME__?: HmrRuntime }).__VUE_HMR_RUNTIME__;
}

/**
 * @private
 */
export function trackSetupHmrComponent(componentName: string): void {
    const instance = getCurrentInstance();

    if (process.env.NODE_ENV === 'production' || !instance || !Shopware.Feature.isActive('ADMIN_VITE_8')) {
        return;
    }

    const instances = mountedInstances.get(componentName) ?? new Set<ComponentInternalInstance>();
    mountedInstances.set(componentName, instances);
    instances.add(instance);

    // The accept handler of a base SFC reads this, it cannot update a built copy that is not mounted.
    const data = (instance.type as HmrComponent).__shopwareSetupHmr?.data;
    if (data) {
        data.instances ??= new Set();
        data.instances.add(instance);
    }

    onUnmounted(() => {
        instances.delete(instance);
        data?.instances?.delete(instance);
    });
}

function remountComponents(componentName: string, runtime: HmrRuntime): void {
    const components = new Map<string, HmrComponent>();

    mountedInstances.get(componentName)?.forEach((instance) => {
        const component = instance.type as HmrComponent;

        if (component.__hmrId) {
            components.set(component.__hmrId, component);
        }
    });

    components.forEach((component, id) => runtime.reload(id, { ...component }));
}

/** The override SFC whose hidden component is setting up, when it was compiled for hot updates. */
function currentOverrideSfc(componentName: string): { owner: string; version: string } | null {
    const component = getCurrentInstance()?.type as HmrComponent | undefined;
    const metadata = component?.__shopwareSetupHmr;

    if (!component?.__hmrId || metadata?.mode !== 'override' || metadata.componentName !== componentName) {
        return null;
    }

    return { owner: component.__hmrId, version: metadata.version };
}

/**
 * Replaces the callback of an edited override SFC and remounts the components it overrides.
 * Returns false when the caller has to add the callback, which includes the first registration of an override SFC.
 *
 * @private
 */
export function registerSetupOverrideForHmr(
    componentName: string,
    callback: OverrideFn,
    overrides: Record<string, OverrideFn[]>,
): boolean {
    const overrideSfc = process.env.NODE_ENV === 'production' ? null : currentOverrideSfc(componentName);
    const runtime = hmrRuntime();

    if (!overrideSfc || !runtime) {
        return false;
    }

    const { owner, version } = overrideSfc;
    const previous = registrations.get(owner);
    const chain = overrides[componentName] ?? [];
    const index = previous ? chain.indexOf(previous.callback) : -1;

    if (index >= 0 && previous?.version === version) {
        return true;
    }

    registrations.set(owner, { callback, version });

    if (index < 0) {
        return false;
    }

    // A new array, so mounted components do not apply the new callback on top of the old one.
    const replacement = [...chain];
    replacement[index] = callback;
    overrides[componentName] = replacement;
    remountComponents(componentName, runtime);

    return true;
}
