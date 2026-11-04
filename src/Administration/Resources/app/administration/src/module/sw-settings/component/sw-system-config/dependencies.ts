/**
 * @sw-package framework
 *
 * Everything sw-system-config needs from outside its mini-module.
 */
/** @private */
export { computed } from 'vue';
/** @private */
export { default as ErrorResolverSystemConfig } from 'src/core/data/error-resolver.system-config.data';
/** @private */
export { deepCloneWithEntity } from 'src/core/service/extension-api-data.service';
/** @private */
export { default as Utils, default as utils } from 'src/core/service/util.service';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification';
/** @private */
export { default as swInlineSnippetMixin } from 'src/app/mixin/sw-inline-snippet';
/** @private */
export { systemConfigApiService } from 'src/app/service-names';
/** @private */
export { mapSystemConfigErrors } from 'src/app/service/map-errors.service';
