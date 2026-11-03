/**
 * @sw-package checkout
 *
 * Everything sw-extension-card-bought needs from outside its mini-module.
 */
import { dateFilterName } from 'src/app/filter/date.filter';

/** @private */
export { default as extensionErrorHandler } from '../../service/extension-error-handler.service';

/** @private */
export const getDateFilter = () => Shopware.Filter.getByName(dateFilterName);
