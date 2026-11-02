/**
 * @sw-package after-sales
 *
 * Everything the document card needs from outside its mini-module.
 */
import { assetFilterName } from 'src/app/filter/asset.filter';
import { dateFilterName } from 'src/app/filter/date.filter';
import { swOrderDetailStoreId } from '../../store/order-detail.store';

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { default as EntityCollection } from 'src/core/data/entity-collection.data';
/**
 * @private
 * @deprecated tag:v6.9.0 - DocumentEvents is deprecated and will be removed.
 */
export { DocumentEvents } from 'src/core/service/api/document.api.service';
/** @private */
export { searchRankingPoint } from 'src/app/service/search-ranking.service';
/** @private */
export { default as fileReaderUtils } from 'src/core/service/utils/file-reader.utils';
/** @private */
export { DOCUMENT_TYPES, FILE_FORMATS } from '../../service/documentV2.service';
/** @private */
export { default as listingMixin } from 'src/app/mixin/listing.mixin';
/** @private */
export { default as placeholderMixin } from 'src/app/mixin/placeholder.mixin';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification.mixin';
/** @private */
export {
    acl as aclService,
    documentService,
    documentV2ApiService,
    documentV2Service,
    numberRangeService,
    repositoryFactory as repositoryFactoryService,
} from 'src/app/service-names';

/** The order detail page's state, which says whether the order is being edited. @private */
export const useOrderDetailStore = () => Shopware.Store.get(swOrderDetailStoreId);

/** @private */
export const getAssetFilter = () => Shopware.Filter.getByName(assetFilterName);

/** @private */
export const getDateFilter = () => Shopware.Filter.getByName(dateFilterName);

/** @private */
export const getApiContext = () => Shopware.Context.api;

/** @private */
export const isFeatureActive = (flag: string) => Shopware.Feature.isActive(flag);
