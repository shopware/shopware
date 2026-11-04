/**
 * @sw-package discovery
 *
 * Everything the video-cover mixin needs from outside its mini-module.
 */
/** @private */
export { acl as aclService, mediaService } from 'src/app/service-names';

/** @private */
export const getEventBus = () => Shopware.Utils.EventBus;
