/**
 * @sw-package framework
 *
 * Everything sw-filter-panel needs from outside its mini-module.
 */
import { filterService } from 'src/app/service-names';

/** @private */
export { feature as featureService, repositoryFactory as repositoryFactoryService } from 'src/app/service-names';

/** @private */
export const getFilterService = (): ServiceContainer['filterService'] => Shopware.Service(filterService);
