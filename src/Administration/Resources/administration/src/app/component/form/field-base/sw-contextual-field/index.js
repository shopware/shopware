import template from './sw-contextual-field.html.twig';
import './sw-contextual-field.scss';

export default {
    name: 'sw-contextual-field',
    template,
    inheritAttrs: false,

    computed: {
        hasPrefix() {
            return this.$scopedSlots.hasOwnProperty('sw-contextual-field-prefix')
                && this.$scopedSlots['sw-contextual-field-prefix']({}) !== undefined;
        },

        hasSuffix() {
            return this.$scopedSlots.hasOwnProperty('sw-contextual-field-suffix')
                && this.$scopedSlots['sw-contextual-field-suffix']({}) !== undefined;
        }
    }
};
