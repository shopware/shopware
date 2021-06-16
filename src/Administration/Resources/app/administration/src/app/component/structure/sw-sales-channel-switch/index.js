import template from './sw-sales-channel-switch.html.twig';

const { Component } = Shopware;
const { debug } = Shopware.Utils;

/**
 * @public
 * @description
 * Renders a sales channel switcher.
 * @status ready
 * @example-type code-only
 * @component-example
 * <sw-sales-channel-switch></sw-sales-channel-info>
 */
Component.register('sw-sales-channel-switch', {
    template,

    props: {
        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
        // FIXME: add default value
        // eslint-disable-next-line vue/require-default-prop
        abortChangeFunction: {
            type: Function,
            required: false,
        },
        // FIXME: add default value
        // eslint-disable-next-line vue/require-default-prop
        saveChangesFunction: {
            type: Function,
            required: false,
        },
        label: {
            type: String,
            required: false,
            default: '',
        },
    },

    data() {
        return {
            salesChannelId: '',
            lastSalesChannelId: '',
            newSalesChannelId: '',
            showUnsavedChangesModal: false,
        };
    },

    methods: {
        onChange(id) {
            this.salesChannelId = id;
            this.newSalesChannelId = id;

            this.checkAbort();
        },
        checkAbort() {
            // Check if abort function exists und reset the select field if the change should be aborted
            if (typeof this.abortChangeFunction === 'function') {
                if (this.abortChangeFunction({
                    oldSalesChannelId: this.lastSalesChannelId,
                    newSalesChannelId: this.salesChannelId,
                })) {
                    this.showUnsavedChangesModal = true;
                    this.salesChannelId = this.lastSalesChannelId;
                    this.$refs.salesChannelSelect.loadSelected();
                    return;
                }
            }

            this.emitChange();
        },
        emitChange() {
            this.lastSalesChannelId = this.salesChannelId;

            this.$emit('change-sales-channel-id', this.salesChannelId);
        },
        onCloseChangesModal() {
            this.showUnsavedChangesModal = false;
            this.newSalesChannelId = '';
        },
        onClickSaveChanges() {
            let save = {};
            // Check if save function exists and wait for it before changing the salesChannel
            if (typeof this.saveChangesFunction === 'function') {
                save = this.saveChangesFunction();
            } else {
                debug.warn('sw-sales-channel-switch', 'You need to implement an own save function to save the changes!');
            }
            return Promise.resolve(save).then(() => {
                this.changeToNewSalesChannel();
                this.onCloseChangesModal();
            });
        },
        onClickRevertUnsavedChanges() {
            this.changeToNewSalesChannel();
            this.onCloseChangesModal();
        },
        changeToNewSalesChannel(salesChannelId) {
            if (salesChannelId) {
                this.newSalesChannelId = salesChannelId;
            }
            this.salesChannelId = this.newSalesChannelId;
            this.newSalesChannelId = '';
            this.$refs.salesChannelSelect.loadSelected();
            this.emitChange();
        },
    },
});
