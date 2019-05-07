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

    model: {
        prop: 'searchTerm',
        event: 'searchTermChanged'
    },

    props: {
        variant: {
            type: String,
            required: false,
            default: 'default',
            validValues: ['default', 'form'],
            validator(value) {
                if (!value.length) {
                    return true;
                }
                return ['default', 'form'].includes(value);
            }
        },

        size: {
            type: String,
            required: false,
            default: 'large',
            validValues: ['large', 'small'],
            validator(value) {
                if (!value.length) {
                    return true;
                }
                return ['large', 'small'].includes(value);
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
        },

        placeholder: {
            type: String,
            required: false
        }
    },

    computed: {
        fieldClasses() {
            return {
                [`sw-simple-search-field--${this.variant}`]: this.variant,
                [`sw-simple-search-field--${this.size}`]: this.size
            };
        },

        fallbackPlaceholder() {
            return this.placeholder || this.$tc('global.sw-simple-search-field.defaultPlaceholder');
        },

        onSearchTermChanged() {
            return utils.debounce((input) => {
                const validInput = input || '';
                this.$emit('searchTermChanged', validInput.trim());
            }, this.delay);
        }
    }
};
