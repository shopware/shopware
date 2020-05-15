import template from './sw-sales-channel-google-authentication.html.twig';
import { getErrorMessage } from '../../helper/get-error-message.helper';

import './sw-sales-channel-google-authentication.scss';

const { Component, State, Service, Mixin } = Shopware;
const { mapState } = Component.getComponentHelper();

Component.register('sw-sales-channel-google-authentication', {
    template,

    mixins: [
        Mixin.getByName('notification')
    ],

    props: {
        salesChannel: {
            type: Object,
            required: true
        }
    },

    data() {
        return {
            isLoading: false,
            isProcessSuccessful: false
        };
    },

    computed: {
        ...mapState('swSalesChannel', [
            'googleShoppingAccount'
        ])
    },

    watch: {
        isLoading: {
            handler: 'updateButtons'
        },

        isProcessSuccessful: {
            handler: 'updateButtons'
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
                    disabled: this.isLoading || this.isProcessSuccessful
                },
                left: {
                    label: this.$tc('sw-sales-channel.modalGooglePrograms.buttonBack'),
                    variant: null,
                    action: 'sw.sales.channel.detail.base.step-1',
                    disabled: this.isLoading || this.isProcessSuccessful
                }
            };

            this.$emit('buttons-update', buttonConfig);
        },

        async onDisconnectAccount() {
            this.isLoading = true;
            this.isProcessSuccessful = false;

            try {
                await Service('googleShoppingService').disconnectGoogle(this.salesChannel.id);

                this.isProcessSuccessful = true;
            } catch (error) {
                const errorDetail = getErrorMessage(error);

                this.createNotificationError({
                    title: this.$tc('sw-sales-channel.modalGooglePrograms.titleError'),
                    message: errorDetail || this.$tc('global.notification.unspecifiedSaveErrorMessage')
                });
            } finally {
                this.isLoading = false;
            }
        },

        processFinish() {
            this.isProcessSuccessful = false;

            State.commit('swSalesChannel/removeGoogleShoppingAccount');
            this.$router.push({ name: 'sw.sales.channel.detail.base.step-1' });
        }
    }
});
