import template from './sw-settings-country-preview-template.html.twig';
import './sw-settings-country-preview-template.scss';

const { Component } = Shopware;

/**
 * @package customer-order
 *
 * @private
 */
Component.register('sw-settings-country-preview-template', {
    template,

    props: {
        formattingAddress: {
            type: String,
            required: true,
        },
    },

    computed: {
        displayFormattingAddress(): string {
            return this.formattingAddress;
        },
    },
});
