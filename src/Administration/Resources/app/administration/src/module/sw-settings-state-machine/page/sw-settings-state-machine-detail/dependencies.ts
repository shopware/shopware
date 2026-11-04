/**
 * @sw-package checkout
 *
 * Everything sw-settings-state-machine-detail needs from outside its mini-module.
 */
/** @private */
export { defineComponent as wrapComponentConfig } from 'vue';
/** @private */
export { default as discardDetailPageChangesMixin } from 'src/app/mixin/discard-detail-page-changes';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification';
/** @private */
export { default as placeholderMixin } from 'src/app/mixin/placeholder';
/** @private */
export { acl as aclService, repositoryFactory as repositoryFactoryService } from 'src/app/service-names';
/** @private */
export { mapPropertyErrors } from 'src/app/service/map-errors.service';
