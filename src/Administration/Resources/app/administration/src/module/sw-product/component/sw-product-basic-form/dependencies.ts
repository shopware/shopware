/**
 * @sw-package inventory
 *
 * Everything sw-product-basic-form needs from outside its mini-module.
 */
import { repositoryFactory, userConfigService } from 'src/app/service-names';
import { contextStoreId } from 'src/app/store/context.store';

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { default as placeholderMixin } from 'src/app/mixin/placeholder.mixin';
/** @private */
export { feature as featureService } from 'src/app/service-names';
/** @private */
export { mapPropertyErrors } from 'src/app/service/map-errors.service';

/** @private */
export const getRepositoryFactory = () => Shopware.Service(repositoryFactory);

/** @private */
export const getUserConfigService = () => Shopware.Service(userConfigService);

/** @private */
export const useContextStore = () => Shopware.Store.get(contextStoreId);

/** @private */
export { useSwProductDetailStore as useProductDetailStore } from '../../page/sw-product-detail/store';

/** @private */
export const getApiContext = () => Shopware.Context.api;
