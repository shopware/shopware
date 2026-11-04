/**
 * @sw-package after-sales
 *
 * Everything sw-flow-sequence-action needs from outside its mini-module.
 */
import { sessionStoreId } from 'src/app/store/session';
import { swFlowStoreId } from '../../store/flow.store';

/** @private */
export { default as orderBy } from 'lodash-es/orderBy';
/** @private */
export { default as sortBy } from 'lodash-es/sortBy';
/** @private */
export { mapState } from 'pinia';
/** @private */
export { default as ShopwareError } from 'src/core/data/ShopwareError';
/** @private */
export { default as Utils } from 'src/core/service/util.service';
/** @private */
export { default as swInlineSnippetMixin } from 'src/app/mixin/sw-inline-snippet.mixin';
/** @private */
export {
    feature as featureService,
    flowBuilderService,
    repositoryFactory as repositoryFactoryService,
} from 'src/app/service-names';

/** @private */
export const useFlowStore = () => Shopware.Store.get(swFlowStoreId);

/** @private */
export const useSessionStore = () => Shopware.Store.get(sessionStoreId);
