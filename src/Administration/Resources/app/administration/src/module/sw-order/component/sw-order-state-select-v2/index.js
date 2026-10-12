import './sw-order-state-select-v2.scss';
import template from './sw-order-state-select-v2.html.twig';

/**
 * @sw-package checkout
 */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['stateStyleDataProviderService'],

    emits: ['state-select'],

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
        stateName: {
            type: String,
            required: false,
            default: null,
        },
        /**
         * @deprecated tag:v6.8.0 - Will be removed without replacement, the select no longer has a rounded style
         */
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
        /**
         * @deprecated tag:v6.8.0 - Will be removed without replacement, the state color is derived from `stateName`
         */
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
        /**
         * @deprecated tag:v6.8.0 - Will be removed without replacement
         */
        selectStyle() {
            return `sw-order-state-select-v2__field${this.roundedStyle ? '--rounded' : ''}`;
        },

        selectPlaceholder() {
            if (this.placeholder) {
                return this.placeholder;
            }
            return this.$t('sw-order.stateCard.labelSelectStatePlaceholder');
        },

        selectable() {
            return !this.disabled && this.transitionOptions.length > 0;
        },

        currentStateOptionId() {
            return this.transitionOptions.find((option) => option.stateName === this.stateName)?.id ?? null;
        },

        selectValue() {
            return this.selectedActionName ?? this.currentStateOptionId;
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

        onSelectValueChange(value) {
            if (value === this.currentStateOptionId) {
                return;
            }

            this.selectedActionName = value;
        },

        getStateVariant(stateName) {
            return this.stateStyleDataProviderService.getStyle(`${this.stateType}.state`, stateName).meteorVariant;
        },
    },
};
