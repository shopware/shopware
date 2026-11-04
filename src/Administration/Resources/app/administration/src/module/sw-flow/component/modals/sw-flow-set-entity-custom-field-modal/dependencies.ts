/**
 * @sw-package after-sales
 *
 * Everything sw-flow-set-entity-custom-field-modal needs from outside its mini-module.
 */
import { sessionStoreId } from 'src/app/store/session';
import { swFlowStoreId } from '../../../store/flow.store';

/** @private */
export { mapState } from 'pinia';
/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { default as ShopwareError } from 'src/core/data/ShopwareError';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification.mixin';
/** @private */
export { default as swInlineSnippetMixin } from 'src/app/mixin/sw-inline-snippet.mixin';
/** @private */
export { flowBuilderService, repositoryFactory as repositoryFactoryService } from 'src/app/service-names';

/** @private */
export const useFlowStore = () => Shopware.Store.get(swFlowStoreId);

/** @private */
export const useSessionStore = () => Shopware.Store.get(sessionStoreId);
