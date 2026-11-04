/**
 * @sw-package inventory
 *
 * Everything sw-product-settings-form needs from outside its mini-module.
 */

/** @private */
export { mapPropertyErrors } from 'src/app/service/map-errors.service';

/** @private */
export { useSwProductDetailStore as useProductDetailStore } from '../../page/sw-product-detail/store';
