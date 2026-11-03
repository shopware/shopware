/**
 * @sw-package checkout
 *
 * Everything sw-time-ago needs from outside its mini-module.
 */
import { dateFilterName } from 'src/app/filter/date.filter';

/** @private */
export { defineComponent as wrapComponentConfig, onUnmounted } from 'vue';

/** @private */
export const getDateFilter = () => Shopware.Filter.getByName(dateFilterName);
