/**
 * @sw-package framework
 *
 * Everything salutation mixin needs from outside its mini-module.
 */
import { salutationFilterName } from 'src/app/filter/salutation.filter';

/** @private */
export { defineComponent } from 'vue';
/** @private */
export type { SalutationFilterEntityType } from 'src/app/composables/use-salutation';

/** @private */
export const getSalutationFilter = () => Shopware.Filter.getByName(salutationFilterName);
