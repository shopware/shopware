import template from './sw-sales-channel-google-store-verification.html.twig';
import './sw-sales-channel-google-store-verification.scss';

const { Component } = Shopware;

Component.register('sw-sales-channel-google-store-verification', {
    template,

    props: {
        salesChannel: {
            type: Object,
            required: true
        }
    },

    data() {
        return {
            items: [
                {
                    status: 'success',
                    errorLink: this.$tc('sw-sales-channel.modalGooglePrograms.step-4.textAdsPolicyUrl'),
                    description: this.$tc('sw-sales-channel.modalGooglePrograms.step-4.textAdsPolicy')
                },
                {
                    status: 'success',
                    errorLink: this.$tc('sw-sales-channel.modalGooglePrograms.step-4.textAccurateContactUrl'),
                    description: this.$tc('sw-sales-channel.modalGooglePrograms.step-4.textAccurateContact')
                },
                {
                    status: 'success',
                    errorLink: this.$tc('sw-sales-channel.modalGooglePrograms.step-4.textSecureCheckoutProcessUrl'),
                    description: this.$tc('sw-sales-channel.modalGooglePrograms.step-4.textSecureCheckoutProcess')
                },
                {
                    status: 'danger',
                    errorLink: this.$tc('sw-sales-channel.modalGooglePrograms.step-4.textReturPolicyUrl'),
                    description: this.$tc('sw-sales-channel.modalGooglePrograms.step-4.textReturPolicy')
                },
                {
                    status: 'success',
                    errorLink: this.$tc('sw-sales-channel.modalGooglePrograms.step-4.textBillingTermsUrl'),
                    description: this.$tc('sw-sales-channel.modalGooglePrograms.step-4.textBillingTerms')
                },
                {
                    status: 'success',
                    errorLink: this.$tc('sw-sales-channel.modalGooglePrograms.step-4.textCompleteUrl'),
                    description: this.$tc('sw-sales-channel.modalGooglePrograms.step-4.textComplete')
                }
            ],
            isLoading: false
        };
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
                    action: this.onClickNext,
                    disabled: false
                },
                left: {
                    label: this.$tc('sw-sales-channel.modalGooglePrograms.buttonBack'),
                    variant: null,
                    action: 'sw.sales.channel.detail.base.step-3',
                    disabled: false
                }
            };

            this.$emit('buttons-update', buttonConfig);
        },

        verifyStore() {
            // TODO: NEXT-7728 Verify the store and enable the NEXT button when all status are okay
        },

        onClickNext() {
            this.$router.push({ name: 'sw.sales.channel.detail.base.step-5' });
        },

        getIconName(status) {
            return (status === 'success') ? 'small-default-checkmark-line-medium' : 'small-default-x-line-medium';
        }
    }
});
