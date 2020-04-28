import template from './sw-sales-channel-google-shipping-setting.html.twig';
import { getErrorMessage } from '../../helper/get-error-message.helper';

import './sw-sales-channel-google-shipping-setting.scss';

const { Component, Utils, Service, Mixin } = Shopware;

Component.register('sw-sales-channel-google-shipping-setting', {
    template,

    mixins: [
        Mixin.getByName('notification')
    ],

    props: {
        salesChannel: {
            type: Object,
            require: true
        }
    },

    data() {
        return {
            selectedSettingOption: null,
            flatRate: 0,
            currency: null,
            settingOptions: [
                {
                    value: 'flatRate',
                    name: this.$tc('sw-sales-channel.modalGooglePrograms.step-6.textFlatRateOption')
                },
                {
                    value: 'selfSetup',
                    name: this.$tc('sw-sales-channel.modalGooglePrograms.step-6.textSelfSetupOption')
                }
            ],
            isLoading: false
        };
    },

    computed: {
        currencyRepository() {
            return Service('repositoryFactory').create('currency');
        },

        currencyIsoCode() {
            return Utils.get(this.currency, 'isoCode');
        }
    },

    watch: {
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
            this.getCurrency(this.salesChannel.productExports[0].currencyId);
            this.selectedSettingOption = this.settingOptions[0].value;
        },

        updateButtons() {
            const buttonConfig = {
                right: {
                    label: this.$tc('sw-sales-channel.modalGooglePrograms.buttonNext'),
                    variant: 'primary',
                    action: this.onClickNext,
                    isLoading: this.isLoading,
                    disabled: false
                },
                left: {
                    label: this.$tc('sw-sales-channel.modalGooglePrograms.buttonBack'),
                    variant: null,
                    action: 'sw.sales.channel.detail.base.step-5',
                    disabled: false
                }
            };

            this.$emit('buttons-update', buttonConfig);
        },

        async onClickNext() {
            this.isLoading = true;

            try {
                if (this.selectedSettingOption === 'flatRate') {
                    await Service('googleShoppingService').setupShipping(this.salesChannel.id, this.flatRate);
                }

                this.$router.push({ name: 'sw.sales.channel.detail.base.step-7' });
            } catch (error) {
                this.showErrorNotification(error);
            } finally {
                this.isLoading = false;
            }
        },

        async getCurrency(currencyId) {
            this.currency = await this.currencyRepository.get(currencyId, Shopware.Context.api);
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

