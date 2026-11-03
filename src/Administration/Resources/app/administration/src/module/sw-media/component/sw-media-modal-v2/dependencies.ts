/**
 * @sw-package discovery
 *
 * Everything sw-media-modal-v2 needs from outside its mini-module.
 */
/** @private */
export {
    feature as featureService,
    mediaService,
    repositoryFactory as repositoryFactoryService,
} from 'src/app/service-names';

/** @private */
export const getApiContext = () => Shopware.Context.api;

/** @private */
export const createId = ((...args: unknown[]) =>
    (Shopware.Utils.createId as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.createId;
