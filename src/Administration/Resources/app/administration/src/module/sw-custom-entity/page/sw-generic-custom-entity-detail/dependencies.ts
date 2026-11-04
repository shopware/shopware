/**
 * @sw-package framework
 *
 * Everything sw-generic-custom-entity-detail needs from outside its mini-module.
 */
import { contextStoreId } from 'src/app/store/context';

/** @private */
export type { TabItem } from '@shopware-ag/meteor-component-library/dist/esm/MtTabs';
/** @private */
export { defineComponent as wrapComponentConfig } from 'vue';
/** @private */
export type { default as Repository } from 'src/core/data/repository.data';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification.mixin';
/** @private */
export { default as placeholderMixin } from 'src/app/mixin/placeholder.mixin';
/** @private */
export {
    acl as aclService,
    customEntityDefinitionService,
    feature as featureService,
    repositoryFactory as repositoryFactoryService,
} from 'src/app/service-names';
/** @private */
export type {
    AdminTabsDefinition,
    AdminUiDefinition,
    CustomEntityDefinition,
    CustomEntityProperties,
} from 'src/app/service/custom-entity-definition.service';

/** @private */
export const useContextStore = () => Shopware.Store.get(contextStoreId);
