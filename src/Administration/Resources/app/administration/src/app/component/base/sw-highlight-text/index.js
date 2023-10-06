import './sw-highlight-text.scss';

const { Component, Context } = Shopware;

/**
 * @package admin
 *
 * @deprecated tag:v6.6.0 - Will be private
 * @public
 * @description This component highlights text based on the searchTerm using regex
 * @status ready
 * @example-type dynamic
 * @component-example
 * <sw-highlight-text text="Lorem ipsum dolor sit amet, consetetur sadipscing elitr" searchTerm="sit"></sw-highlight-text>
 */
Component.register('sw-highlight-text', {
    template: '',

    render(createElement) {
        return createElement(
            'div',
            {
                class: 'sw-highlight-text',
                domProps: { innerHTML: this.searchAndReplace() },
            },
        );
    },

    props: {
        searchTerm: {
            type: String,
            required: false,
            default: null,
        },
        text: {
            type: String,
            required: false,
            default: null,
        },
    },

    methods: {
        searchAndReplace() {
            if (!this.text) {
                return '';
            }

            if (!this.searchTerm) {
                return this.text;
            }

            const prefix = '<span class="sw-highlight-text__highlight">';
            const suffix = '</span>';

            const regExp = new RegExp(this.escapeRegExp(this.searchTerm), 'ig');
            return this.text.replace(regExp, str => `${prefix}${str}${suffix}`);
        },

        // Remove regex special characters from search string
        escapeRegExp(string) {
            if (Context.app.adminEsEnable) {
                // remove simple query string syntax
                return string.replace(/[+-.*~"|()]/g, '').replace(/ AND | and | OR | or |  +/g, ' ').replace(/[?^${}[\]\\]/g, '\\$&'); // $& means the whole matched string
            }

            return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); // $& means the whole matched string
        },
    },
});
