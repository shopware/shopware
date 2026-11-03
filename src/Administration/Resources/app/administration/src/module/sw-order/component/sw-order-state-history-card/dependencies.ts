/**
 * @sw-package checkout
 *
 * Everything sw-order-state-history-card needs from outside its mini-module.
 */
/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification.mixin';
/** @private */
export {
    acl as aclService,
    orderService,
    orderStateMachineService,
    repositoryFactory as repositoryFactoryService,
    stateMachineService,
} from 'src/app/service-names';

/** @private */
export const isFeatureActive = (flag: string) => Shopware.Feature.isActive(flag);
