/**
 * @sw-package inventory
 *
 * Everything sw-product-seo-form needs from outside its mini-module.
 */

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { default as placeholderMixin } from 'src/app/mixin/placeholder.mixin';
/** @private */
export { repositoryFactory as repositoryFactoryService } from 'src/app/service-names';
/** @private */
export { mapPropertyErrors } from 'src/app/service/map-errors.service';

/** @private */
export { useSwProductDetailStore as useProductDetailStore } from '../../page/sw-product-detail/store';
