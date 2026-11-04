/**
 * @sw-package discovery
 *
 * Everything sw-category-detail-seo needs from outside its mini-module.
 */

/** @private */
export { acl as aclService } from 'src/app/service-names';

/** @private */
export { useSwCategoryDetailStore as useCategoryDetailStore } from '../../page/sw-category-detail/store';
