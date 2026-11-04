/**
 * @sw-package framework
 *
 * Everything sw-settings-logging-list needs from outside its mini-module.
 */
import { dateFilterName } from 'src/app/filter/date.filter';

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { default as placeholderMixin } from 'src/app/mixin/placeholder.mixin';
/** @private */
export { repositoryFactory as repositoryFactoryService } from 'src/app/service-names';
/** @private */
export { default as swSettingsListMixin } from 'src/module/sw-settings/mixin/sw-settings-list/private'; // eslint-disable-line sw-core-rules/mini-module-boundaries -- the mixin is private, extensions reach it with Mixin.getByName()

/** @private */
export const getDateFilter = () => Shopware.Filter.getByName(dateFilterName);
