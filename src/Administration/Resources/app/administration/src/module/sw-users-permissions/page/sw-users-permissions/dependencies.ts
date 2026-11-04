/**
 * @sw-package fundamentals@framework
 *
 * Everything sw-users-permissions needs from outside its mini-module.
 */
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification';
/** @private */
export { acl as aclService, feature as featureService } from 'src/app/service-names';
