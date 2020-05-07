import template from './sw-sales-channel-google-done-verification.html.twig';
import './sw-sales-channel-google-done-verification.scss';

const { Component } = Shopware;

Component.register('sw-sales-channel-google-done-verification', {
    template,

    props: {
        salesChannel: {
            type: Object,
            required: true
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.updateButtons();
        },

        updateButtons() {
            const buttonConfig = {
                right: {
                    label: this.$tc('sw-sales-channel.modalGooglePrograms.buttonFinish'),
                    variant: 'primary',
                    action: this.onCloseModal,
                    disabled: false
                },
                left: {
                    label: this.$tc('sw-sales-channel.modalGooglePrograms.buttonBack'),
                    variant: null,
                    action: 'sw.sales.channel.detail.base.step-6',
                    disabled: false
                }
            };

            this.$emit('buttons-update', buttonConfig);
        },

        onCloseModal() {
            this.$emit('modal-close');
        }
    }
});
