/**
 * @sw-package checkout
 *
 * Everything sw-order-state-history-card-entry needs from outside its mini-module.
 */
import { dateFilterName } from 'src/app/filter/date.filter';

/** @private */
export { stateStyleDataProviderService } from 'src/app/service-names';

/** @private */
export const getDateFilter = () => Shopware.Filter.getByName(dateFilterName);
