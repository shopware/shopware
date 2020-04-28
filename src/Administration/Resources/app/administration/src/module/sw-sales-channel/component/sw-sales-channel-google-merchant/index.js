import template from './sw-sales-channel-google-merchant.html.twig';
import { getErrorMessage } from '../../helper/get-error-message.helper';

import './sw-sales-channel-google-merchant.scss';

const { Component, State, Service, Mixin, Utils } = Shopware;
const { mapState, mapGetters } = Component.getComponentHelper();

Component.register('sw-sales-channel-google-merchant', {
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
            merchantAccounts: [],
            selectedMerchant: '',
            isListLoading: false,
            isProcessLoading: false,
            isProcessSuccessful: false
        };
    },

    computed: {
        ...mapState('swSalesChannel', [
            'googleShoppingAccount'
        ]),

        ...mapGetters('swSalesChannel', [
            'googleShoppingMerchantAccount'
        ]),

        compoundData() {
            const {
                isProcessLoading,
                isListLoading,
                selectedMerchant,
                isProcessSuccessful
            } = this;

            return {
                isProcessLoading,
                isListLoading,
                selectedMerchant,
                isProcessSuccessful
            };
        }
    },

    watch: {
        compoundData: {
            deep: true,
            handler: 'updateButtons'
        }
    },

    created() {
        this.createdComponent();
    },

    mounted() {
        this.mountedComponent();
    },

    methods: {
        createdComponent() {
            if (this.googleShoppingMerchantAccount) {
                this.selectedMerchant = this.googleShoppingMerchantAccount.merchantId;
            }

            this.updateButtons();
        },

        mountedComponent() {
            this.getMerchantList();
        },

        updateButtons() {
            const buttonConfig = {
                right: {
                    label: this.$tc('sw-sales-channel.modalGooglePrograms.buttonNext'),
                    variant: 'primary',
                    action: this.onClickNext,
                    disabled: this.isListLoading || !this.selectedMerchant || this.isProcessLoading || this.isProcessSuccessful,
                    isLoading: this.isProcessLoading,
                    isProcessSuccessful: this.isProcessSuccessful,
                    processFinish: this.processFinish
                },
                left: {
                    label: this.$tc('sw-sales-channel.modalGooglePrograms.buttonBack'),
                    variant: null,
                    action: 'sw.sales.channel.detail.base.step-2',
                    disabled: this.isProcessLoading
                }
            };

            this.$emit('buttons-update', buttonConfig);
        },

        async getMerchantList() {
            this.isListLoading = true;

            try {
                const { data: merchantAccounts } = await Service('googleShoppingService')
                    .getMerchantList(this.salesChannel.id);

                this.merchantAccounts = Utils.get(merchantAccounts, 'data', []);
            } catch (error) {
                this.showErrorNotification(error);
            } finally {
                this.isListLoading = false;
            }
        },

        async onClickNext() {
            const merchantId = Utils.get(this.googleShoppingMerchantAccount, 'merchantId', '');

            if (merchantId && merchantId === this.selectedMerchant) {
                this.$router.push({ name: 'sw.sales.channel.detail.base.step-4' });
                return;
            }

            this.isProcessLoading = true;
            this.isProcessSuccessful = false;

            try {
                if (merchantId) {
                    await Service('googleShoppingService').unassignMerchant(this.salesChannel.id);

                    State.commit('swSalesChannel/setGoogleShoppingMerchantAccount', null);
                }

                await Service('googleShoppingService')
                    .assignMerchant(this.salesChannel.id, this.selectedMerchant);

                const googleShoppingMerchantAccount = {
                    ...this.googleShoppingMerchantAccount,
                    merchantId: this.selectedMerchant
                };

                State.commit('swSalesChannel/setGoogleShoppingMerchantAccount', googleShoppingMerchantAccount);

                this.isProcessSuccessful = true;
            } catch (error) {
                this.showErrorNotification(error);
            } finally {
                this.isProcessLoading = false;
            }
        },

        processFinish() {
            this.isProcessSuccessful = false;
            this.$router.push({ name: 'sw.sales.channel.detail.base.step-4' });
        },

        getMerchantItemLabel(item) {
            return `${item.id} - ${item.name}`;
        },

        showErrorNotification(error) {
            const errorDetail = getErrorMessage(error);

            this.createNotificationError({
                title: this.$tc('sw-sales-channel.modalGooglePrograms.titleError'),
                message: errorDetail || this.$tc('global.notification.unspecifiedSaveErrorMessage')
            });
        }
    }
});
