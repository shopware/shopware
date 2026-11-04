/**
 * @sw-package fundamentals@discovery
 *
 * Everything sw-settings-language-list needs from outside its mini-module.
 */
import { cacheService } from 'src/app/service-names';

/** @private */
export { useSnackbar } from '@shopware-ag/meteor-component-library';
/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { default as listingMixin } from 'src/app/mixin/listing.mixin';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification.mixin';
/** @private */
export {
    acl as aclService,
    feature as featureService,
    repositoryFactory as repositoryFactoryService,
    translationService,
} from 'src/app/service-names';

/** @private */
export const getCacheService = () => Shopware.Service(cacheService);

/** @private */
export const getApiContext = () => Shopware.Context.api;

/** @private */
export const localeName = ((...args: unknown[]) =>
    (Shopware.Utils.format.localeName as (...args: unknown[]) => unknown)(
        ...args,
    )) as typeof Shopware.Utils.format.localeName;
