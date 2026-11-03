/**
 * @sw-package discovery
 *
 * Everything sw-cms-page-form needs from outside its mini-module.
 */
/** @private */
export { defineComponent as wrapComponentConfig } from 'vue';
/** @private */
export type { PropType } from 'vue';
/** @private */
export { cmsService } from 'src/app/service-names';
/** @private */
export { default as CMS } from '../../constant/sw-cms.constant';
/** @private */
export { default as cmsStateMixin } from '../../mixin/sw-cms-state.mixin';
