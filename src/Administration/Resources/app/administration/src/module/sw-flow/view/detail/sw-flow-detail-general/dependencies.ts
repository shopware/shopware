/**
 * @sw-package after-sales
 *
 * Everything sw-flow-detail-general needs from outside its mini-module.
 */
import { swFlowStoreId } from '../../../store/flow.store';

/** @private */
export { mapState } from 'pinia';
/** @private */
export { acl as aclService } from 'src/app/service-names';
/** @private */
export { mapPropertyErrors } from 'src/app/service/map-errors.service';

/** @private */
export const useFlowStore = () => Shopware.Store.get(swFlowStoreId);
