/**
 * @sw-package discovery
 *
 * Everything sw-settings-snippet-list needs from outside its mini-module.
 */
import { userConfigService } from 'src/app/service-names';

/** @private */
export { default as Sanitizer } from 'src/core/helper/sanitizer.helper';
/** @private */
export {
    acl as aclService,
    repositoryFactory as repositoryFactoryService,
    snippetService,
    snippetSetService,
    userService,
} from 'src/app/service-names';

/** @private */
export const getUserConfigService = () => Shopware.Service(userConfigService);
