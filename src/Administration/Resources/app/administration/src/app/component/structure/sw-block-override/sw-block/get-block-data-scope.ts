import { getCurrentInstance } from 'vue';

/**
 * @sw-package framework
 * @private
 */
export default function getBlockDataScope() {
    return getCurrentInstance()?.proxy ?? null;
}
