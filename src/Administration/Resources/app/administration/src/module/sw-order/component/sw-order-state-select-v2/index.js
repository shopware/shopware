import './sw-order-state-select-v2.scss';
import template from './sw-order-state-select-v2.html.twig';

/**
 * @package customer-order
 */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,
    props: {
        transitionOptions: {
            type: Array,
            required: false,
            default() {
                return [];
            },
        },
        stateType: {
            type: String,
            required: true,
        },
        roundedStyle: {
            type: Boolean,
            required: false,
            default: false,
        },
        placeholder: {
            type: String,
            required: false,
            default: null,
        },
        label: {
            type: String,
            required: false,
            default: null,
        },
        backgroundStyle: {
            type: String,
            required: false,
            default: '',
        },
        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
    },
    data() {
        return {
            selectedActionName: null,
        };
    },
    computed: {
        selectStyle() {
            return `sw-order-state-select-v2__field${this.roundedStyle ? '--rounded' : ''}`;
        },

        selectPlaceholder() {
            if (this.placeholder) {
                return this.placeholder;
            }
            return this.$tc('sw-order.stateCard.labelSelectStatePlaceholder');
        },

        selectable() {
            return !this.disabled && this.transitionOptions.length > 0;
        },
    },
    watch: {
        selectedActionName() {
            if (this.selectedActionName !== null) {
                this.onStateChangeClicked();
            }
        },
    },

    methods: {
        onStateChangeClicked() {
            this.$emit('state-select', this.stateType, this.selectedActionName);

            this.$nextTick(() => {
                this.selectedActionName = null;
            });
        },
    },
};
