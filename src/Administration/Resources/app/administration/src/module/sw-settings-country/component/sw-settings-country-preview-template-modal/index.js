import template from './sw-settings-country-preview-template-modal.html.twig';
import { FORMAT_ADDRESS_TEMPLATE } from '../../constant/address.constant';

const { Component } = Shopware;

Component.register('sw-settings-country-preview-template-modal', {
    template,

    inject: [
        'countryAddressService',
    ],

    props: {
        country: {
            type: Object,
            required: true,
        },

        previewData: {
            type: Object,
            required: true,
        },
    },

    data() {
        return {
            previewAddress: null,
        };
    },

    computed: {
        displayFormattingAddress() {
            return this.previewAddress ? this.previewAddress.replace(/\n/g, '<br>') : '';
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.populateData();
        },

        populateData() {
            const address = {
                ...this.previewData?.defaultBillingAddress,
                country: this.country,
            };

            if (this.country.useDefaultAddressFormat) {
                this.renderDefaultContent(address);

                return;
            }

            this.countryAddressService.formattingAddress(address).then((res) => {
                this.previewAddress = res;
            });
        },

        renderDefaultContent(address) {
            this.countryAddressService.previewTemplate(address, FORMAT_ADDRESS_TEMPLATE).then((res) => {
                this.previewAddress = res;
            });
        },

        onCancelShowPreview() {
            this.$emit('modal-close');
        },
    },
});
