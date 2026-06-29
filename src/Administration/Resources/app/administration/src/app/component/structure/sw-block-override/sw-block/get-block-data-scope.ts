import { getCurrentInstance } from 'vue';
import { getScriptSetupDataScope } from 'src/app/adapter/composition-extension-data-scope';

/**
 * @sw-package framework
 * @private
 */
export default function getBlockDataScope() {
    const instance = getCurrentInstance();

    if (!instance) {
        return null;
    }

    return getScriptSetupDataScope(instance) ?? instance.proxy ?? null;
}
