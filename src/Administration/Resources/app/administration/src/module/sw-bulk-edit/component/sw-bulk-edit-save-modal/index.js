import template from './sw-bulk-edit-save-modal.html.twig';
import './sw-bulk-edit-save-modal.scss';

const { Component } = Shopware;

Component.register('sw-bulk-edit-save-modal', {
    template,

    props: {
        itemTotal: {
            required: true,
            type: Number,
        },
        isLoading: {
            required: true,
            type: Boolean,
        },
        processStatus: {
            required: true,
            type: String,
        },
    },

    data() {
        return {
            title: null,
            buttonConfig: [],
        };
    },

    computed: {
        currentStep() {
            if (this.isLoading && !this.processStatus) {
                return 'process';
            }

            if (!this.isLoading && this.processStatus === 'success') {
                return 'success';
            }

            if (!this.isLoading && this.processStatus === 'fail') {
                return 'fail';
            }

            return 'confirm';
        },

        buttons() {
            return {
                right: this.buttonConfig.filter((button) => button.position === 'right'),
                left: this.buttonConfig.filter((button) => button.position === 'left'),
            };
        },
    },

    watch: {
        currentStep(value) {
            if (value === 'success') {
                this.redirect('success');
            }

            if (value === 'fail') {
                this.redirect('error');
            }
        },
    },

    methods: {
        onModalClose() {
            this.$emit('modal-close');
        },

        applyChanges() {
            this.$emit('bulk-save');
        },

        redirect(routeName) {
            if (!routeName) {
                this.$emit('modal-close');
            }

            this.$router.push({ path: routeName });
        },

        setTitle(title) {
            this.title = title;
        },

        updateButtons(buttonConfig) {
            this.buttonConfig = buttonConfig;
        },

        onButtonClick(action) {
            if (typeof action === 'string') {
                this.redirect(action);
                return;
            }

            if (typeof action !== 'function') {
                return;
            }

            action.call();
        },
    },
});
