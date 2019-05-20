import utils from 'src/core/service/util.service';
import template from './sw-simple-search-field.html.twig';
import './sw-simple-search-field.scss';

/**
 * @public
 * @description a search field with delayed update
 * @status ready
 * @example-type static
 * @component-example
 * <sw-simple-search-field :delay="1000"></sw-simple-search-field>
 */
export default {
    name: 'sw-simple-search-field',
    template,
    inheritAttrs: false,

    model: {
        prop: 'searchTerm',
        event: 'search-term-changed'
    },

    props: {
        variant: {
            type: String,
            required: false,
            default: 'default',
            validValues: ['default', 'inverted', 'form'],
            validator(value) {
                if (!value.length) {
                    return true;
                }
                return ['default', 'inverted', 'form'].includes(value);
            }
        },

        searchTerm: {
            type: String,
            required: false
        },

        delay: {
            type: Number,
            required: false,
            default: 400
        },

        icon: {
            type: String,
            required: false,
            default: 'small-search'
        }
    },

    computed: {
        fieldClasses() {
            return [
                `sw-simple-search-field--${this.variant}`
            ];
        },

        placeholder() {
            return this.$attrs.placeholder || this.$tc('global.sw-simple-search-field.defaultPlaceholder');
        },

        onSearchTermChanged() {
            return utils.debounce((input) => {
                const validInput = input || '';
                this.$emit('search-term-changed', validInput.trim());
            }, this.delay);
        }
    }
};
