/**
 * @sw-package fundamentals@after-sales
 *
 * Everything sw-import-export-view-profiles needs from outside its mini-module.
 */
/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification.mixin';
/** @private */
export {
    feature as featureService,
    importExport as importExportService,
    repositoryFactory as repositoryFactoryService,
} from 'src/app/service-names';

/** @private */
export const getApiContext = () => Shopware.Context.api;
