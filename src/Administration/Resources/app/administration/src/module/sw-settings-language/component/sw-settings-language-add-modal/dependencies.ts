/**
 * @sw-package fundamentals@discovery
 *
 * Everything sw-settings-language-add-modal needs from outside its mini-module.
 */
/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification.mixin';
/** @private */
export { repositoryFactory as repositoryFactoryService, translationService } from 'src/app/service-names';

/** @private */
export const localeName = ((...args: unknown[]) =>
    (Shopware.Utils.format.localeName as (...args: unknown[]) => unknown)(
        ...args,
    )) as typeof Shopware.Utils.format.localeName;
