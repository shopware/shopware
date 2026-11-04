/**
 * @sw-package inventory
 *
 * Everything sw-product-visibility-detail needs from outside its mini-module.
 */
import { truncateFilterName } from 'src/app/filter/truncate.filter';

/** @private */
export { useSwProductDetailStore as useProductDetailStore } from '../../page/sw-product-detail/store';

/** @private */
export const getTruncateFilter = () => Shopware.Filter.getByName(truncateFilterName);
