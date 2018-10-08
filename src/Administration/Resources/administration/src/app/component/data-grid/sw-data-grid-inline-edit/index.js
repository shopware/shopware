import template from './sw-data-grid-inline-edit.html.twig';
import './sw-data-grid-inline-edit.scss';

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

            this.$parent.$on('inline-edit-assign', () => {
                this.$emit('input', this.currentValue);
            });
        }
    }
};
