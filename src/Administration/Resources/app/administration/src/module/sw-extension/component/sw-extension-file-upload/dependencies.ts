/**
 * @sw-package checkout
 *
 * Everything sw-extension-file-upload needs from outside its mini-module.
 */
import { userConfigService } from 'src/app/service-names';

/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification';
/** @private */
export { extensionStoreActionService } from 'src/app/service-names';
/** @private */
export { default as pluginErrorHandler } from '../../service/extension-error-handler.service';

/** @private */
export const getUserConfigService = () => Shopware.Service(userConfigService);
