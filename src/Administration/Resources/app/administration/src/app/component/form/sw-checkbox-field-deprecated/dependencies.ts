/**
 * @sw-package framework
 *
 * Everything sw-checkbox-field-deprecated needs from outside its mini-module.
 */
/** @private */
export { inject } from 'vue';
/** @private */
export { default as Utils } from 'src/core/service/util.service';
/** @private */
export { default as swFormFieldMixin } from 'src/app/mixin/sw-form-field';
/** @private */
export { default as removeApiErrorMixin } from 'src/app/mixin/remove-api-error';
/** @private */
export { feature as featureService } from 'src/app/service-names';
