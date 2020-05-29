import template from './sw-sales-channel-google-terms-verification.html.twig';
import { getErrorMessage } from '../../helper/get-error-message.helper';

import './sw-sales-channel-google-terms-verification.scss';

const { Component, Service, Mixin, State } = Shopware;
const { mapGetters } = Component.getComponentHelper();

Component.register('sw-sales-channel-google-terms-verification', {
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
            isAgree: false,
            isLoading: false
        };
    },

    computed: {
        ...mapGetters('swSalesChannel', [
            'termsOfServiceAccept'
        ])
    },

    watch: {
        isAgree: {
            handler: 'updateButtons'
        },

        isLoading: {
            handler: 'updateButtons'
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.updateButtons();

            this.isAgree = !!this.termsOfServiceAccept;
        },

        updateButtons() {
            const buttonConfig = {
                right: {
                    label: this.$tc('sw-sales-channel.modalGooglePrograms.buttonNext'),
                    variant: 'primary',
                    action: this.onClickNext,
                    isLoading: this.isLoading,
                    disabled: !this.isAgree
                },
                left: {
                    label: this.$tc('sw-sales-channel.modalGooglePrograms.buttonBack'),
                    variant: null,
                    action: 'sw.sales.channel.detail.base.step-4',
                    disabled: false
                }
            };

            this.$emit('buttons-update', buttonConfig);
        },

        async onClickNext() {
            this.isLoading = true;

            try {
                await Service('googleShoppingService').saveTermsOfService(this.salesChannel.id, this.isAgree);

                State.commit('swSalesChannel/setTermsOfService', this.isAgree);

                this.$router.push({ name: 'sw.sales.channel.detail.base.step-6' });
            } catch (error) {
                this.showErrorNotification(error);
            } finally {
                this.isLoading = false;
            }
        },

        showErrorNotification(error) {
            const errorDetail = getErrorMessage(error);

            this.createNotificationError({
                title: this.$tc('global.default.error'),
                message: errorDetail || this.$tc('global.notification.unspecifiedSaveErrorMessage')
            });
        }
    }
});
