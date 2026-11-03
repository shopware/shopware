/**
 * @sw-package framework
 */
import template from './sw-bulk-edit-form-field-renderer.html.twig';

/**
 * @private
 */
export default {
    template,

    emits: ['update:default-unit'],

    methods: {
        onUpdateDefaultUnit(unit) {
            this.$emit('update:default-unit', { unit, config: this.config });
        },
    },
};
