/**
 * @sw-package framework
 *
 * Everything sw-duplicated-media-v2 needs from outside its mini-module.
 */
import { dateFilterName } from 'src/app/filter/date.filter';
import { fileSizeFilterName } from 'src/app/filter/file-size.filter';
import { contextStoreId } from 'src/app/store/context.store';

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export {
    mediaPresignedUploadService,
    mediaService,
    repositoryFactory as repositoryFactoryService,
} from 'src/app/service-names';

/** @private */
export const useContextStore = () => Shopware.Store.get(contextStoreId);

/** @private */
export const getDateFilter = () => Shopware.Filter.getByName(dateFilterName);

/** @private */
export const getFileSizeFilter = () => Shopware.Filter.getByName(fileSizeFilterName);

/** @private */
export const getApiContext = () => Shopware.Context.api;
