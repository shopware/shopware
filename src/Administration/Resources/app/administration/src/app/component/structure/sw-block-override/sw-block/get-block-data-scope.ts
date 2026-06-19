import { getCurrentInstance } from 'vue';
import type { ComponentInternalInstance } from 'vue';
import { scriptSetupDataScopeKey } from 'src/app/adapter/composition-extension-system';

type ComponentInstanceWithScriptSetupDataScope = ComponentInternalInstance & {
    [scriptSetupDataScopeKey]?: Record<string, unknown>;
};

/**
 * @sw-package framework
 * @private
 */
export default function getBlockDataScope() {
    const instance = getCurrentInstance() as ComponentInstanceWithScriptSetupDataScope | null;

    return instance?.[scriptSetupDataScopeKey] ?? instance?.proxy ?? null;
}
