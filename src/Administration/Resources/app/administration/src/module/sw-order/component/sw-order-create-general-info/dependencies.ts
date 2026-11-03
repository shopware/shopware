/**
 * @sw-package checkout
 *
 * Everything sw-order-create-general-info needs from outside its mini-module.
 */
import { currencyFilterName } from 'src/app/filter/currency.filter';

/** @private */
export { defineComponent as wrapComponentConfig } from 'vue';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification.mixin';
/** @private */
export { acl as aclService } from 'src/app/service-names';
/** @private */
export type { Cart, SalesChannelContext } from '../../order.types';

/** @private */
export const getCurrencyFilter = () => Shopware.Filter.getByName(currencyFilterName);
