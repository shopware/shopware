/**
 * @sw-package inventory
 *
 * Everything sw-seo-url-template-card needs from outside its mini-module.
 */
/** @private */
export { default as Utils } from 'src/core/service/util.service';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification';
/** @private */
export { repositoryFactory as repositoryFactoryService, seoUrlTemplateService } from 'src/app/service-names';
/** @private */
export { mapCollectionPropertyErrors } from 'src/app/service/map-errors.service';

/** @private */
export const getApiContext = () => Shopware.Context.api;
