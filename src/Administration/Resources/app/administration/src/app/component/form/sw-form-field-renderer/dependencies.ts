/**
 * @sw-package framework
 *
 * Everything sw-form-field-renderer needs from outside its mini-module.
 */
/** @private */
export { default as swInlineSnippetMixin } from 'src/app/mixin/sw-inline-snippet.mixin';
/** @private */
export { feature as featureService, repositoryFactory as repositoryFactoryService } from 'src/app/service-names';

/** @private */
export const getApiContext = () => Shopware.Context.api;

/** @private */
export const getAppContext = () => Shopware.Context.app;

/** @private */
export const isUndefined = (...args: Parameters<typeof Shopware.Utils.types.isUndefined>) =>
    Shopware.Utils.types.isUndefined(...args);
