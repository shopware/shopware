/**
 * @sw-package framework
 *
 * Everything sw-settings-custom-field-set-create needs from outside its mini-module.
 */
/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { default as Utils } from 'src/core/service/util.service';

/** @private */
export const getApiContext = () => Shopware.Context.api;
