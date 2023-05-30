import template from './sw-flow-grant-download-access-modal.html.twig';

const { Component, Mixin } = Shopware;
const { ShopwareError } = Shopware.Classes;
const { mapState } = Component.getComponentHelper();

/**
 * @private
 * @package business-ops
 */
export default {
    template,

    mixins: [
        Mixin.getByName('placeholder'),
        Mixin.getByName('notification'),
    ],

    props: {
        sequence: {
            type: Object,
            required: true,
        },
        action: {
            type: String,
            required: false,
            default: null,
        },
    },

    data() {
        return {
            value: null,
            valueError: null,
        };
    },

    computed: {
        valueOptions() {
            return [
                {
                    value: true,
                    label: `${this.$tc('sw-flow.modals.downloadAccess.options.grant')}`,
                },
                {
                    value: false,
                    label: `${this.$tc('sw-flow.modals.downloadAccess.options.revoke')}`,
                },
            ];
        },

        ...mapState('swFlowState', ['triggerEvent', 'triggerActions']),
    },

    watch: {
        value(value) {
            if (value && this.valueError) {
                this.valueError = null;
            }
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            const { config } = this.sequence;

            this.value = config?.value;
        },

        getConfig() {
            return {
                value: this.value,
            };
        },

        fieldError(field) {
            if (typeof field !== 'boolean') {
                return new ShopwareError({
                    code: 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                });
            }

            return null;
        },

        onSave() {
            this.valueError = this.fieldError(this.value);
            if (this.valueError) {
                return;
            }

            const config = this.getConfig();
            const data = {
                ...this.sequence,
                config,
            };
            this.$emit('process-finish', data);
            this.onClose();
        },

        onClose() {
            this.valueError = null;
            this.$emit('modal-close');
        },
    },
};
