/**
 * @sw-package fundamentals@after-sales
 *
 * Everything sw-condition-date-range needs from outside its mini-module.
 */
import { timezoneService } from 'src/app/service-names';

/** @private */
export const getTimezoneService = (): ServiceContainer['timezoneService'] => Shopware.Service(timezoneService);
