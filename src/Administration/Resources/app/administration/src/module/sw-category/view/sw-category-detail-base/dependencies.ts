/**
 * @sw-package discovery
 *
 * Everything sw-category-detail-base needs from outside its mini-module.
 */

/** @private */
export { default as placeholderMixin } from 'src/app/mixin/placeholder';
/** @private */
export { acl as aclService, repositoryFactory as repositoryFactoryService } from 'src/app/service-names';
/** @private */
export { mapPropertyErrors } from 'src/app/service/map-errors.service';

/** @private */
export { useSwCategoryDetailStore as useCategoryDetailStore } from '../../page/sw-category-detail/store';
