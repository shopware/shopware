import { getCurrentInstance } from 'vue';

/**
 * @package admin
 * @private
 */
export default function getBlockDataScope() {
    return getCurrentInstance()?.proxy ?? null;
}
