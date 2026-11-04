/**
 * @sw-package framework
 *
 * Everything sw-datepicker-deprecated needs from outside its mini-module.
 */
import { sessionStoreId } from 'src/app/store/session';

/** @private */
export { utcToZonedTime, zonedTimeToUtc } from 'date-fns-tz';
/** @private */
export { default as swFormFieldMixin } from 'src/app/mixin/sw-form-field';
/** @private */
export { default as removeApiErrorMixin } from 'src/app/mixin/remove-api-error';
/** @private */
export { feature as featureService } from 'src/app/service-names';

/** @private */
export const useSessionStore = () => Shopware.Store.get(sessionStoreId);
