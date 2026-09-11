/** @sw-package framework */
import { computed, getCurrentInstance, onBeforeMount, unref, type ComponentInternalInstance, type ComputedRef } from 'vue';
import { getScriptSetupDataScope } from '../composition-extension-system/data-scope-helper';

type AssetKind = 'components' | 'directives';
type AssetOwner = ComponentInternalInstance & {
    components: Record<string, unknown> | null;
    directives: Record<string, unknown> | null;
};
const normalize = (name: string) => name.replace(/-/g, '').toLowerCase();

/**
 * Resolve SFC lexical and Options local assets together. Registration belongs to this instance,
 * never the shared SFC definition, because a setup-local component can close over instance state.
 * @private
 */
export function resolveLegacyAsset(
    kind: AssetKind,
    name: string,
    binding: string,
    fallback: () => unknown,
): ComputedRef<unknown> {
    const owner = getCurrentInstance() as AssetOwner | null;
    const value = computed(() => {
        const options = owner && typeof owner.type !== 'function' ? owner.type[kind] : undefined;
        const override = Object.keys(options ?? {}).find((key) => normalize(key) === normalize(name));
        if (override) return options![override] as unknown;
        const state = (owner ? getScriptSetupDataScope(owner) : undefined) as Record<string, unknown> | undefined;
        return unref(state && binding in state ? state[binding] : fallback());
    });
    if (owner) {
        const register = () => {
            const registrations = { ...owner[kind] };
            Object.defineProperty(registrations, name, { configurable: true, enumerable: true, get: () => value.value });
            owner[kind] = registrations;
        };
        // Vue applies Options registrations after setup. Restore the lexical fallback before rendering.
        onBeforeMount(register, owner);
    }
    return value;
}
