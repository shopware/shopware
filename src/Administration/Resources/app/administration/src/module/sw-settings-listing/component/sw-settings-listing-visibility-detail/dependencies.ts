/**
 * @sw-package inventory
 *
 * Everything sw-settings-listing-visibility-detail needs from outside its mini-module.
 */
import { truncateFilterName } from 'src/app/filter/truncate.filter';

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { repositoryFactory as repositoryFactoryService } from 'src/app/service-names';

/** @private */
export const getTruncateFilter = () => Shopware.Filter.getByName(truncateFilterName);
