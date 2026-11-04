/**
 * @sw-package framework
 *
 * Everything sw-form-field-renderer needs from outside its mini-module.
 */
/** @private */
export { default as swInlineSnippetMixin } from 'src/app/mixin/sw-inline-snippet';
/** @private */
export { feature as featureService, repositoryFactory as repositoryFactoryService } from 'src/app/service-names';

/** @private */
export const getApiContext = () => Shopware.Context.api;

/** @private */
export const getAppContext = () => Shopware.Context.app;

/** @private */
export const isUndefined = ((...args: unknown[]) =>
    (Shopware.Utils.types.isUndefined as (...args: unknown[]) => unknown)(
        ...args,
    )) as typeof Shopware.Utils.types.isUndefined;
