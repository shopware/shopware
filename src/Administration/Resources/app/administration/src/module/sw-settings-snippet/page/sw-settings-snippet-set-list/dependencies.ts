/**
 * @sw-package discovery
 *
 * Everything sw-settings-snippet-set-list needs from outside its mini-module.
 */
import { dateFilterName } from 'src/app/filter/date.filter';

/** @private */
export {
    acl as aclService,
    feature as featureService,
    repositoryFactory as repositoryFactoryService,
    snippetSetService,
} from 'src/app/service-names';

/** @private */
export const getDateFilter = () => Shopware.Filter.getByName(dateFilterName);
