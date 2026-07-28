import type { ComponentInternalInstance } from 'vue';
import { getCurrentInstance } from 'vue';
import { getScriptSetupDataScope } from 'src/app/adapter/composition-extension-system/data-scope-helper';
import { applyOverridesForOptionsHost } from 'src/app/adapter/composition-extension-system/options-host-overrides';

/**
 * @sw-package framework
 * @private
 *
 * Resolves the data object exposed to `sw-block` slots for the current component instance.
 *
 * Native setup components register a proxy-compatible data scope outside Vue's public instance proxy.
 * Options API components fall back to the instance proxy — extended with the `__swOverride` channel
 * when native setup overrides target them, so override-local state stays reachable on such hosts too.
 */

const optionsHostScopeByInstance = new WeakMap<ComponentInternalInstance, object>();

/**
 * Returns the component name a native override would have been registered under for this host.
 */
function getHostComponentName(instance: ComponentInternalInstance): string | null {
    const type = instance.type as { name?: string };

    return type.name ?? null;
}

/**
 * Wraps an Options API host proxy so slot data also resolves the `__swOverride` channel.
 *
 * All other reads keep delegating to the live instance proxy; the wrapper identity is cached per
 * instance so repeated renders reuse the same scope object.
 */
function getOptionsHostScope(instance: ComponentInternalInstance, proxy: object): object {
    const componentName = getHostComponentName(instance);
    const overrideLocalState = componentName ? applyOverridesForOptionsHost(componentName, proxy) : null;

    if (!overrideLocalState) {
        return proxy;
    }

    let scope = optionsHostScopeByInstance.get(instance);

    if (!scope) {
        scope = new Proxy(proxy, {
            get(target, key, receiver) {
                if (key === '__swOverride') {
                    return overrideLocalState;
                }

                return Reflect.get(target, key, receiver) as unknown;
            },
            has(target, key) {
                return key === '__swOverride' || Reflect.has(target, key);
            },
        });
        optionsHostScopeByInstance.set(instance, scope);
    }

    return scope;
}

/**
 * @private
 *
 * Resolves the block slot data scope for the currently rendering component instance.
 */
export default function getBlockDataScope() {
    const instance = getCurrentInstance();

    if (!instance) {
        return null;
    }

    const scriptSetupScope = getScriptSetupDataScope(instance);

    if (scriptSetupScope) {
        return scriptSetupScope;
    }

    if (!instance.proxy) {
        return null;
    }

    return getOptionsHostScope(instance, instance.proxy);
}
