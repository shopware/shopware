import template from './sw-grid-row.html.twig';
import './sw-grid-row.scss';

const { Component } = Shopware;
const utils = Shopware.Utils;

/**
 * @package admin
 *
 * @private
 */
Component.register('sw-grid-row', {
    template,

    inject: {
        swGridInlineEditStart: {
            from: 'swGridInlineEditStart',
            default: null,
        },
        swGridInlineEditCancel: {
            from: 'swGridInlineEditCancel',
            default: null,
        },
        swOnInlineEditStart: {
            from: 'swOnInlineEditStart',
            default: null,
        },
        swRegisterGridDisableInlineEditListener: {
            from: 'swRegisterGridDisableInlineEditListener',
            default: null,
        },
        swUnregisterGridDisableInlineEditListener: {
            from: 'swUnregisterGridDisableInlineEditListener',
            default: null,
        },
    },

    props: {
        item: {
            type: Object,
            required: true,
        },

        index: {
            type: Number,
            required: false,
            default: null,
        },

        allowInlineEdit: {
            type: Boolean,
            required: false,
            // eslint-disable-next-line vue/no-boolean-default
            default: true,
        },
    },

    data() {
        return {
            columns: [],
            isEditingActive: false,
            inlineEditingCls: 'is--inline-editing',
            id: utils.createId(),
        };
    },

    watch: {
        isEditingActive() {
            if (this.isEditingActive) {
                this.$refs.swGridRow.classList.add(this.inlineEditingCls);
                return;
            }

            this.$refs.swGridRow.classList.remove(this.inlineEditingCls);
        },
    },

    created() {
        this.createdComponent();
    },

    beforeUnmount() {
        if (!this.isCompatEnabled('INSTANCE_EVENT_EMITTER')) {
            this.swUnregisterGridDisableInlineEditListener(this.onInlineEditCancel);
        }
    },

    methods: {
        createdComponent() {
            // Bubble up columns declaration for the column header definition
            this.$parent.columns = this.columns;

            if (this.isCompatEnabled('INSTANCE_EVENT_EMITTER')) {
                this.$parent.$on('sw-grid-disable-inline-editing', (id) => {
                    this.onInlineEditCancel(id);
                });
            } else {
                this.swRegisterGridDisableInlineEditListener(this.onInlineEditCancel);
            }
        },

        onInlineEditStart() {
            if (!this.allowInlineEdit || this.$device.getViewportWidth() < 960) {
                return;
            }

            let isInlineEditingConfigured = false;

            // If inline editing is already enabled, or no column has
            // the property "editable" we don't have to enable it.
            this.columns.forEach((column) => {
                if (column.editable || isInlineEditingConfigured) {
                    isInlineEditingConfigured = true;
                }
            });

            if (this.isEditingActive || !isInlineEditingConfigured) {
                return;
            }

            this.isEditingActive = true;
            if (this.isCompatEnabled('INSTANCE_EVENT_EMITTER')) {
                this.$parent.$emit('sw-row-inline-edit-start', this.id);
                this.$parent.$emit('inline-edit-start', this.item);
            } else {
                this.swGridInlineEditStart(this.id);
                this.swOnInlineEditStart(this.item);
            }
        },

        onInlineEditCancel(id, index) {
            if (id && id !== this.id) {
                return;
            }

            this.isEditingActive = false;
            this.$parent.$emit('sw-row-inline-edit-cancel', this.id, index);
            this.$parent.$emit('inline-edit-cancel', this.item, index);
        },

        onInlineEditFinish() {
            this.isEditingActive = false;
            this.$emit('inline-edit-finish', this.item);
        },
    },
});
