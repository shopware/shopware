/**
 * @sw-package fundamentals@discovery
 *
 * Everything sw-settings-country-create needs from outside its mini-module.
 */
/** @private */
export { default as Utils } from 'src/core/service/util.service';

/** @private */
export const getApiContext = () => Shopware.Context.api;

/** @private */
export const getAppContext = () => Shopware.Context.app;
