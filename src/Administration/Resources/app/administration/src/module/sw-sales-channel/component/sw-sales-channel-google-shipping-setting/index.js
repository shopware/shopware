import template from './sw-sales-channel-google-shipping-setting.html.twig';
import './sw-sales-channel-google-shipping-setting.scss';

const { Component, Utils, Service } = Shopware;

Component.register('sw-sales-channel-google-shipping-setting', {
    template,

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
                    value: 'currentStore',
                    name: this.$tc('sw-sales-channel.modalGooglePrograms.step-7.textCurrentStoreOption')
                },
                {
                    value: 'flatRate',
                    name: this.$tc('sw-sales-channel.modalGooglePrograms.step-7.textFlatRateOption')
                },
                {
                    value: 'selfSetup',
                    name: this.$tc('sw-sales-channel.modalGooglePrograms.step-7.textSelfSetupOption')
                }
            ]
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

        onClickNext() {
            // TODO: Integrate assign shipping setting logic
        },

        async getCurrency(currencyId) {
            this.currency = await this.currencyRepository.get(currencyId, Shopware.Context.api);
        }
    }
});

