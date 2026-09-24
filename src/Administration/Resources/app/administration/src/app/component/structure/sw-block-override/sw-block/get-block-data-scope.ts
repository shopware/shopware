import { getCurrentInstance, type ComponentInternalInstance } from 'vue';
import { getScriptSetupDataScope } from 'src/app/adapter/composition-extension-system/data-scope-helper';

/**
 * @sw-package framework
 * @private
 *
 * The data scope `sw-block` passes to its layers: the setup state of a native setup component, the instance
 * proxy of any other component. Without an argument it resolves the current instance, which is how the
 * `$dataScope` global property uses it.
 */
export default function getBlockDataScope(instance: ComponentInternalInstance | null = getCurrentInstance()) {
    if (!instance) {
        return null;
    }

    return getScriptSetupDataScope(instance) ?? instance.proxy ?? null;
}
