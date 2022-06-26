import template from './sw-settings-country-preview-template.html.twig';
import './sw-settings-country-preview-template.scss';

const { Component } = Shopware;
const utils = Shopware.Utils;
const { get } = utils.object;

Component.register('sw-settings-country-preview-template', {
    template,

    props: {
        previewData: {
            type: Object,
            required: false,
            default: () => {},
        },

        advancedAddressFormat: {
            type: Array,
            required: true,
        },
    },

    computed: {
        displayFormattingAddress() {
            return this.advancedAddressFormat.map((snippet) => {
                return snippet.map(
                    item => get(this.previewData, item.value, item.label),
                ).join(' ');
            });
        },
    },
});
