/**
 * @sw-package fundamentals@after-sales
 *
 * Everything sw-settings-rule-add-assignment-listing needs from outside its mini-module.
 */
/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { cloneDeep } from 'src/core/service/utils/object.utils';

/** @private */
export const getApiContext = () => Shopware.Context.api;
