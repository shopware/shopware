/**
 * @sw-package discovery
 *
 * Everything sw-sales-channel-list needs from outside its mini-module.
 */
import { dateFilterName } from 'src/app/filter/date.filter';

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { default as EventBus } from 'src/core/service/utils/eventBus.utils';
/** @private */
export { default as listingMixin } from 'src/app/mixin/listing';
/** @private */
export { acl as aclService, domainLinkService, repositoryFactory as repositoryFactoryService } from 'src/app/service-names';

/** @private */
export const getDateFilter = () => Shopware.Filter.getByName(dateFilterName);
