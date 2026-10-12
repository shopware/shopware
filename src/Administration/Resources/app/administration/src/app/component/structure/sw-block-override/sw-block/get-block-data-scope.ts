import { getCurrentInstance } from 'vue';
import { getScriptSetupDataScope } from 'src/app/adapter/composition-extension-system/data-scope-helper';

/**
 * @sw-package framework
 * @private
 *
 * Resolves the data object exposed to `sw-block` slots for the current component instance.
 *
 * Native setup components register a proxy-compatible data scope outside Vue's public instance proxy,
 * while Options API components keep using the proxy fallback.
 */
export default function getBlockDataScope() {
    const instance = getCurrentInstance();

    if (!instance) {
        return null;
    }

    return getScriptSetupDataScope(instance) ?? instance.proxy ?? null;
}
