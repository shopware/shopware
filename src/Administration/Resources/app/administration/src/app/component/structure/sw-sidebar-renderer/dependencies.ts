/**
 * @sw-package framework
 *
 * Everything sw-sidebar-renderer needs from outside its mini-module.
 */
import { sidebarStoreId } from 'src/app/store/sidebar.store';

/** @private */
export { createFocusTrap } from 'focus-trap';
/** @private */
export type { FocusTrap } from 'focus-trap';
/** @private */
export {
    computed,
    defineComponent as wrapComponentConfig,
    nextTick,
    onMounted,
    onUnmounted,
    ref,
    shallowRef,
    watch,
} from 'vue';
/** @private */
export type { ComponentPublicInstance } from 'vue';

/** @private */
export const useSidebarStore = () => Shopware.Store.get(sidebarStoreId);
