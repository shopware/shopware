/**
 * @sw-package discovery
 *
 * Everything sw-generic-cms-page-assignment needs from outside its mini-module.
 */
import { cmsPageStoreId } from 'src/module/sw-cms/store/cms-page.store';

/** @private */
export { default as Criteria } from '@shopware-ag/meteor-admin-sdk/es/data/Criteria';
/** @private */
export { defineComponent as wrapComponentConfig } from 'vue';
/** @private */
export type { default as ChangesetGenerator } from 'src/core/data/changeset-generator.data';
/** @private */
export type { default as Repository } from 'src/core/data/repository.data';
/** @private */
export { object } from 'src/core/service/util.service';
/** @private */
export { cmsPageTypeService, repositoryFactory as repositoryFactoryService } from 'src/app/service-names';

/** @private */
export const useCmsPageStore = () => Shopware.Store.get(cmsPageStoreId);
