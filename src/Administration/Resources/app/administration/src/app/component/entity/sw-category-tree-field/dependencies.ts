/**
 * @sw-package framework
 *
 * Everything sw-category-tree-field needs from outside its mini-module.
 */
/** @private */
export { default as Data } from 'src/core/data';
/** @private */
export { default as Utils } from 'src/core/service/util.service';
/** @private */
export { repositoryFactory as repositoryFactoryService } from 'src/app/service-names';

/** @private */
export const getApiContext = () => Shopware.Context.api;
