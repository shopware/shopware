import template from './sw-sales-channel-google-merchant.html.twig';
import './sw-sales-channel-google-merchant.scss';

const { Component, State } = Shopware;

Component.register('sw-sales-channel-google-merchant', {
    template,

    props: {
        salesChannel: {
            type: Object,
            required: true
        }
    },

    data() {
        return {
            merchantAccounts: [],
            selectedMerchant: '',
            isListLoading: false
        };
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
                    action: this.onClickNext,
                    disabled: false
                },
                left: {
                    label: this.$tc('sw-sales-channel.modalGooglePrograms.buttonBack'),
                    variant: null,
                    action: 'sw.sales.channel.detail.base.step-2',
                    disabled: false
                }
            };

            this.$emit('buttons-update', buttonConfig);
        },

        onClickNext() {
            // TODO: Integrate assign google merchant logic
        }
    }
});
