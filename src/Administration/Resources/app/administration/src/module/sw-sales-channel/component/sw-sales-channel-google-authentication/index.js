import template from './sw-sales-channel-google-authentication.html.twig';
import './sw-sales-channel-google-authentication.scss';

const { Component, State } = Shopware;

Component.register('sw-sales-channel-google-authentication', {
    template,

    props: {
        salesChannel: {
            type: Object,
            required: true
        }
    },

    computed: {
        userProfile() {
            return State.get('swSalesChannel').googleShoppingAccount;
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
                    label: this.$tc('sw-sales-channel.modalGooglePrograms.buttonNext'),
                    variant: 'primary',
                    action: 'sw.sales.channel.detail.base.step-3',
                    disabled: false
                },
                left: {
                    label: this.$tc('sw-sales-channel.modalGooglePrograms.buttonBack'),
                    variant: null,
                    action: 'sw.sales.channel.detail.base.step-1',
                    disabled: false
                }
            };

            this.$emit('buttons-update', buttonConfig);
        },

        onDisconnectAccount() {
            // TODO: Call disconnect account API
            this.$router.push({ name: 'sw.sales.channel.detail.base.step-1' });
        }
    }
});
