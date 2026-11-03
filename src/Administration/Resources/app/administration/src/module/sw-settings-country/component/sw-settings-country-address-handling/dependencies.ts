/**
 * @sw-package fundamentals@discovery
 *
 * Everything sw-settings-country-address-handling needs from outside its mini-module.
 */
/** @private */
export { default as camelCase } from 'lodash-es/camelCase';
/** @private */
export { defineComponent as wrapComponentConfig } from 'vue';
/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export type { default as CriteriaType } from 'src/core/data/criteria.data';
/** @private */
export { cloneDeep } from 'src/core/service/utils/object.utils';
/** @private */
export type { DragConfig } from 'src/app/directive/dragdrop.directive';
/** @private */
export { acl as aclService, customSnippetApiService } from 'src/app/service-names';
