/**
 * @sw-package framework
 *
 * Everything sw-settings-message-stats needs from outside its mini-module.
 */
/** @private */
export { defineComponent } from 'vue';
/** @private */
export type {
    default as MessageStatsApiService,
    MessageStatsResponse,
} from 'src/core/service/api/message-stats.api.service';
/** @private */
export { date } from 'src/core/service/utils/format.utils';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification';
/** @private */
export { messageStatsService } from 'src/app/service-names';
