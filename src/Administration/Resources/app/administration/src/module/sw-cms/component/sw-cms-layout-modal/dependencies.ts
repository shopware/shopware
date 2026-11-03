/**
 * @sw-package discovery
 *
 * Everything sw-cms-layout-modal needs from outside its mini-module.
 */
import { dateFilterName } from 'src/app/filter/date.filter';

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { default as listingMixin } from 'src/app/mixin/listing.mixin';
/** @private */
export {
    acl as aclService,
    cmsPageTypeService,
    repositoryFactory as repositoryFactoryService,
    systemConfigApiService,
} from 'src/app/service-names';

/** @private */
export const getDateFilter = () => Shopware.Filter.getByName(dateFilterName);
