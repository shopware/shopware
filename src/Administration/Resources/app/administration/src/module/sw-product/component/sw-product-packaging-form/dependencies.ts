/**
 * @sw-package inventory
 *
 * Everything sw-product-packaging-form needs from outside its mini-module.
 */

/** @private */
export { default as placeholderMixin } from 'src/app/mixin/placeholder.mixin';
/** @private */
export { mapPropertyErrors } from 'src/app/service/map-errors.service';

/** @private */
export { useSwProductDetailStore as useProductDetailStore } from '../../page/sw-product-detail/store';
