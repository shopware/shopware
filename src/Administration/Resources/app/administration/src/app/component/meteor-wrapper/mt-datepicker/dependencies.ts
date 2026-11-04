/**
 * @sw-package framework
 *
 * Everything mt-datepicker needs from outside its mini-module.
 */
import { sessionStoreId } from 'src/app/store/session';

/** @private */
export { default as MtDatepickerOriginal } from '@shopware-ag/meteor-component-library/dist/esm/MtDatepicker';
/** @private */
export { defineComponent as wrapComponentConfig } from 'vue';
/** @private */
export type { DateTimeOptions } from 'vue-i18n';

/** @private */
export const useSessionStore = () => Shopware.Store.get(sessionStoreId);
