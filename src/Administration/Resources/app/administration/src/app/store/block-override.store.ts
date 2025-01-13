/**
 * @package customer-order
 * @private
 */
import useBlockContext from '../composables/use-block-context';

/**
 * @private
 */
const blockOverrideStore = Shopware.Store.register('blockOverride', useBlockContext);

/**
 * @private
 */
export default blockOverrideStore;

/**
 * @private
 */
export type BlockOverrideStore = ReturnType<typeof blockOverrideStore>;
