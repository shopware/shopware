/**
 * @sw-package discovery
 *
 * Everything sw-sales-channel-detail-base needs from outside its mini-module.
 */
import { dateFilterName } from 'src/app/filter/date.filter';
import { timezoneService } from 'src/app/service-names';
import { errorStoreId } from 'src/app/store/error.store';

/** @private */
export { default as Data } from 'src/core/data';
/** @private */
export { default as Utils, dom, object } from 'src/core/service/util.service';
/** @private */
export { default as EventBus } from 'src/core/service/utils/eventBus.utils';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification.mixin';
/** @private */
export { default as placeholderMixin } from 'src/app/mixin/placeholder.mixin';
/** @private */
export { mapPropertyErrors } from 'src/app/service/map-errors.service';

/** @private */
export const getTimezoneService = (): ServiceContainer['timezoneService'] => Shopware.Service(timezoneService);

/** @private */
export const useErrorStore = () => Shopware.Store.get(errorStoreId);

/** @private */
export const getDateFilter = () => Shopware.Filter.getByName(dateFilterName);

/** @private */
export const getApiContext = () => Shopware.Context.api;
