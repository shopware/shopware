/**
 * @sw-package framework
 *
 * Everything sw-code-editor needs from outside its mini-module.
 */
/** @private */
export { default as Utils } from 'src/core/service/util.service';
/** @private */
export { feature as featureService, userInputSanitizeService } from 'src/app/service-names';

/** @private */
export const getAppContext = () => Shopware.Context.app;
