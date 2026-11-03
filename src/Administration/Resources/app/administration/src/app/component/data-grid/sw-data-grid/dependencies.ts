/**
 * @sw-package framework
 *
 * Everything sw-data-grid needs from outside its mini-module.
 */
import { userConfigService } from 'src/app/service-names';

/** @private */
export { default as Utils } from 'src/core/service/util.service';
/** @private */
export { default as translateWithFallbackMixin } from 'src/app/mixin/translate-with-fallback.mixin';
/** @private */
export {
    acl as aclService,
    feature as featureService,
    repositoryFactory as repositoryFactoryService,
} from 'src/app/service-names';

/** @private */
export const getUserConfigService = () => Shopware.Service(userConfigService);

/** @private */
export const debounce = (...args: Parameters<typeof Shopware.Utils.debounce>) => Shopware.Utils.debounce(...args);
