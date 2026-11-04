/**
 * @sw-package fundamentals@discovery
 *
 * Everything sw-settings-country-detail needs from outside its mini-module.
 */
import { userConfigService } from 'src/app/service-names';

/** @private */
export { set } from 'src/core/service/utils/object.utils';
/** @private */
export { default as discardDetailPageChangesMixin } from 'src/app/mixin/discard-detail-page-changes';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification';
/** @private */
export { default as placeholderMixin } from 'src/app/mixin/placeholder';
/** @private */
export {
    acl as aclService,
    customFieldDataProviderService,
    feature as featureService,
    repositoryFactory as repositoryFactoryService,
} from 'src/app/service-names';
/** @private */
export { mapPropertyErrors } from 'src/app/service/map-errors.service';

/** @private */
export const getUserConfigService = () => Shopware.Service(userConfigService);

/** @private */
export const getApiContext = () => Shopware.Context.api;
