/**
 * @sw-package framework
 *
 * Everything sw-user-card needs from outside its mini-module.
 */
import { salutationFilterName } from 'src/app/filter/salutation.filter';

/** @private */
export const getSalutationFilter = () => Shopware.Filter.getByName(salutationFilterName);
