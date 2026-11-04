/**
 * @sw-package framework
 *
 * Everything sw-app-shop-id-change-modal needs from outside its mini-module.
 */
/** @private */
export { defineComponent as wrapComponentConfig } from 'vue';
/** @private */
export type { ShopIdCheck, Strategy } from 'src/core/service/api/shop-id-change.service';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification';
/** @private */
export { shopIdChangeService } from 'src/app/service-names';
