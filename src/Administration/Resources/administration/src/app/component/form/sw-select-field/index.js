import template from './sw-select-field.html.twig';

/**
 * @public
 * @description select input field.
 * @status ready
 * @example-type static
 * @component-example
 * <sw-select-field placeholder="placeholder goes here..." :label="label"
     v-model="model">
     <slot">
        <option v-for="operator in operators" :value="operator.id">
        {{ option.label }}
        </option>
     </slot>
    </sw-select-field>
 */
export default {
    name: 'sw-select-field',
    extendsFrom: 'sw-text-field',
    template,

    props: {
        options: {
            type: Array,
            required: false
        }
    },

    computed: {
        typeFieldClass() {
            return 'sw-field--select';
        }
    }
};
