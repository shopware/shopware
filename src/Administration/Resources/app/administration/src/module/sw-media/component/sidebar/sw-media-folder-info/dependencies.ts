/**
 * @sw-package discovery
 *
 * Everything sw-media-folder-info needs from outside its mini-module.
 */
/** @private */
export { acl as aclService, repositoryFactory as repositoryFactoryService } from 'src/app/service-names';
/** @private */
export { mapPropertyErrors } from 'src/app/service/map-errors.service';
/** @private */
export { default as mediaSidebarModalMixinMixin } from '../../../mixin/media-sidebar-modal-mixin/private'; // eslint-disable-line sw-core-rules/mini-module-boundaries -- the mixin is private, extensions reach it with Mixin.getByName()

/** @private */
export const getApiContext = () => Shopware.Context.api;

/** @private */
export const date = ((...args: unknown[]) =>
    (Shopware.Utils.format.date as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.format.date;
