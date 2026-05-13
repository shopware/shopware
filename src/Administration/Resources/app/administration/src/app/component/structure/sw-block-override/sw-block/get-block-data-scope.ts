import { getCurrentInstance } from 'vue';
import type { ComponentInternalInstance } from 'vue';

type ComponentInstanceWithScriptSetupDataScope = ComponentInternalInstance & {
    __shopwareSetupDataScope?: Record<string, unknown>;
};

/**
 * @sw-package framework
 * @private
 */
export default function getBlockDataScope() {
    const instance = getCurrentInstance() as ComponentInstanceWithScriptSetupDataScope | null;

    return instance?.__shopwareSetupDataScope ?? instance?.proxy ?? null;
}
