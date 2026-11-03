/**
 * @sw-package checkout
 *
 * Everything sw-order-send-document-modal needs from outside its mini-module.
 */
import { dateFilterName } from 'src/app/filter/date.filter';
import { truncateFilterName } from 'src/app/filter/truncate.filter';

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { default as EntityCollection } from 'src/core/data/entity-collection.data';
/** @private */
export { mailService, repositoryFactory as repositoryFactoryService } from 'src/app/service-names';
/** @private */
export { DOCUMENT_TYPES } from '../../service/documentV2.service';

/** @private */
export const getDateFilter = () => Shopware.Filter.getByName(dateFilterName);

/** @private */
export const getTruncateFilter = () => Shopware.Filter.getByName(truncateFilterName);

/** @private */
export const getApiContext = () => Shopware.Context.api;

/** @private */
export const isFeatureActive = (flag: string) => Shopware.Feature.isActive(flag);
