/**
 * @sw-package inventory
 *
 * Everything sw-manufacturer-detail needs from outside its mini-module.
 */
import { contextStoreId } from 'src/app/store/context';

/** @private */
export {
    acl as aclService,
    customFieldDataProviderService,
    feature as featureService,
    mediaDefaultFolderService,
    repositoryFactory as repositoryFactoryService,
} from 'src/app/service-names';
/** @private */
export { mapPropertyErrors } from 'src/app/service/map-errors.service';

/** @private */
export const useContextStore = () => Shopware.Store.get(contextStoreId);

/** @private */
export const publishData = ((...args: unknown[]) =>
    (Shopware.ExtensionAPI.publishData as (...args: unknown[]) => unknown)(
        ...args,
    )) as typeof Shopware.ExtensionAPI.publishData;
