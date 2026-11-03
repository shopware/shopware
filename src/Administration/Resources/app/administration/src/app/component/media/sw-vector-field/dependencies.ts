/**
 * @sw-package innovation
 *
 * Everything sw-vector-field needs from outside its mini-module.
 */
/** @private */
export { defineComponent as wrapComponentConfig } from 'vue';
/** @private */
export { default as swFormFieldMixin } from 'src/app/mixin/form-field.mixin';
/** @private */
export { default as removeApiErrorMixin } from 'src/app/mixin/remove-api-error.mixin';
/** @private */
export { default as validationMixin } from 'src/app/mixin/validation.mixin';
/** @private */
export { feature as featureService } from 'src/app/service-names';
