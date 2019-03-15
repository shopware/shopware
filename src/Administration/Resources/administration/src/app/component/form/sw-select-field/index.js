import template from './sw-select-field.html.twig';

/**
 * @public
 * @description select input field.
 * @status ready
 * @example-type static
 * @component-example
 * <sw-select-field placeholder="placeholder goes here..." label="label">
 *     <option value="value1">Label #1</option>
 *     <option value="value2">Label #2</option>
 *     <option value="value3">Label #3</option>
 *     <option value="value4">Label #4</option>
 *     <option value="value5">Label #5</option>
 * </sw-select-field>
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
    },

    methods: {
        getOptionName(name) {
            if (name) {
                if (name[this.locale]) {
                    return name[this.locale];
                }

                if (name[this.fallbackLocale]) {
                    return name[this.fallbackLocale];
                }

                return name;
            }

            return '';
        }
    }
};
