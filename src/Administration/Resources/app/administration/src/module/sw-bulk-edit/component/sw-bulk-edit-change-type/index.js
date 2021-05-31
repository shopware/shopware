import template from './sw-bulk-edit-change-type.html.twig';
import './sw-bulk-edit-change-type.scss';

const { Component } = Shopware;

Component.register('sw-bulk-edit-change-type', {
    template,

    model: {
        prop: 'value',
        event: 'change',
    },

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
                this.$emit('change', newValue);
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
});
