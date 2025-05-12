import template from './sw-settings-country-preview-template.html.twig';
import './sw-settings-country-preview-template.scss';

const { Component } = Shopware;

/**
 * @sw-package fundamentals@discovery
 *
 * @private
 */
export default Component.wrapComponentConfig({
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
