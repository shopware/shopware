/**
 * @sw-package framework
 */
import type { InjectionKey, Ref, Slot } from 'vue';

/**
 * @private
 */
export default Symbol('parents') as InjectionKey<Ref<ReturnType<Slot>[]>>;
