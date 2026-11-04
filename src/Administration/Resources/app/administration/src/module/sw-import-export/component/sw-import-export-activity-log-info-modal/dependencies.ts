/**
 * @sw-package fundamentals@after-sales
 *
 * Everything sw-import-export-activity-log-info-modal needs from outside its mini-module.
 */
import { dateFilterName } from 'src/app/filter/date.filter';

/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification';
/** @private */
export { importExport as importExportService } from 'src/app/service-names';

/** @private */
export const getDateFilter = () => Shopware.Filter.getByName(dateFilterName);

/** @private */
export const fileSize = ((...args: unknown[]) =>
    (Shopware.Utils.format.fileSize as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.format.fileSize;
