/** @sw-package framework */
import type { InjectionKey, Slot } from 'vue';

/** @private */
export default Symbol('parentBlock') as InjectionKey<Slot>;
