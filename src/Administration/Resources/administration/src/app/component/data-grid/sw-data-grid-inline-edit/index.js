import template from './sw-data-grid-inline-edit.html.twig';
import './sw-data-grid-inline-edit.scss';

/**
 * @private
 */
export default {
    name: 'sw-data-grid-inline-edit',

    template,

    props: {
        column: {
            type: Object,
            required: true,
            default() {
                return {};
            }
        },
        value: {
            required: true
        },
        compact: {
            type: Boolean,
            required: false,
            default: false
        }
    },

    data() {
        return {
            currentValue: null
        };
    },

    created() {
        this.createdComponent();
    },

    beforeDestroy() {
        this.beforeDestroyedComponent();
    },

    computed: {
        classes() {
            return {
                'is--compact': this.compact
            };
        },

        inputFieldSize() {
            return this.compact ? 'small' : 'default';
        }
    },

    methods: {
        createdComponent() {
            this.currentValue = this.value;

            this.$parent.$on('inline-edit-assign', this.emitInput);
        },

        beforeDestroyedComponent() {
            this.$parent.$off('inline-edit-assign', this.emitInput);
        },

        emitInput() {
            this.$emit('input', this.currentValue);
        }
    }
};
