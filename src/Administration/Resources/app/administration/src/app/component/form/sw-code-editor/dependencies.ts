/**
 * @sw-package framework
 *
 * Everything sw-code-editor needs from outside its mini-module.
 */
/** @private */
export { default as Ace } from 'ace-builds/src-noconflict/ace';
/** @private */
export { setCompleters, textCompleter } from 'ace-builds/src-noconflict/ext-language_tools';
/** @private */
export { default as Utils } from 'src/core/service/util.service';
/** @private */
export { feature as featureService, userInputSanitizeService } from 'src/app/service-names';

/** @private */
export const getAppContext = () => Shopware.Context.app;
