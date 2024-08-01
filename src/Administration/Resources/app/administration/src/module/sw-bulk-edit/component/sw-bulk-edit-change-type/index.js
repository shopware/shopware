/**
 * @package services-settings
 */
import template from './sw-bulk-edit-change-type.html.twig';
import './sw-bulk-edit-change-type.scss';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    compatConfig: Shopware.compatConfig,

    inject: ['feature'],

    props: {
        value: {
            type: String,
            required: true,
        },
        allowOverwrite: {
            type: Boolean,
            required: false,
            default: false,
        },
        allowClear: {
            type: Boolean,
            required: false,
            default: false,
        },
        allowAdd: {
            type: Boolean,
            required: false,
            default: false,
        },
        allowRemove: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            isDisplayingValue: true,
        };
    },

    computed: {
        currentValue: {
            get() {
                return this.value;
            },
            set(newValue) {
                this.isDisplayingValue = newValue !== 'clear';

                this.$emit('update:value', newValue);
            },
        },

        options() {
            const options = [];
            if (this.allowOverwrite) {
                options.push({
                    value: 'overwrite',
                    label: this.$tc('sw-bulk-edit.changeTypes.overwrite'),
                });
            }

            if (this.allowClear) {
                options.push({
                    value: 'clear',
                    label: this.$tc('sw-bulk-edit.changeTypes.clear'),
                });
            }

            if (this.allowAdd) {
                options.push({
                    value: 'add',
                    label: this.$tc('sw-bulk-edit.changeTypes.add'),
                });
            }

            if (this.allowRemove) {
                options.push({
                    value: 'remove',
                    label: this.$tc('sw-bulk-edit.changeTypes.remove'),
                });
            }

            return options;
        },
    },

    methods: {
        onChangeType(value) {
            this.currentValue = value;
        },
    },
};
