/**
 * @sw-package discovery
 *
 * Everything sw-cms-el-buy-box needs from outside its mini-module.
 */
import { currencyFilterName } from 'src/app/filter/currency.filter';

/** @private */
export { default as placeholderMixin } from 'src/app/mixin/placeholder.mixin';
/** @private */
export { default as cmsElementMixin } from '../../../mixin/sw-cms-element.mixin';

/** @private */
export const getCurrencyFilter = () => Shopware.Filter.getByName(currencyFilterName);
