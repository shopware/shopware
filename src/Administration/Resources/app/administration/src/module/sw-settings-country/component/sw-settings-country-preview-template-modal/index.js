import template from './sw-settings-country-preview-template-modal.html.twig';
import { FORMAT_ADDRESS_TEMPLATE } from '../../constant/address.constant';

const { Component } = Shopware;

Component.register('sw-settings-country-preview-template-modal', {
    template,

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

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            const content = this.country.useDefaultAddressFormat
                ? FORMAT_ADDRESS_TEMPLATE
                : this.country?.advancedAddressFormatPlain;

            this.populateData(content);
        },

        populateData(content) {
            this.previewAddress = content;

            // TODO: NEXT-21001 - Use build template service instead
            Object.entries(this.previewData).forEach(([key, value]) => {
                const regex = new RegExp(`{{\\s*?${key}\\s*?}}`, 'g');
                this.previewAddress = this.previewAddress.replace(regex, value).replace('\n', '<br/>')
                    .replace(null, '').replace(undefined, '');
            });
        },

        onCancelShowPreview() {
            this.$emit('modal-close');
        },
    },
});
