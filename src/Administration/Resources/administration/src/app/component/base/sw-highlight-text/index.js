import { Component } from 'src/core/shopware';
import template from './sw-highlight-text.html.twig';
import './sw-highlight-text.scss';


/**
 * @public
 * @description This component highlights text based on the searchTerm using regex
 * @status ready
 * @example-type dynamic
 * @component-example
 * <sw-highlight-text text="Lorem ipsum dolor sit amet, consetetur sadipscing elitr" searchTerm="sit"></sw-highlight-text>
 */
Component.register('sw-highlight-text', {
    template,

    props: {
        searchTerm: {
            type: String,
            required: false,
            default: null
        },
        text: {
            type: String,
            required: false,
            default: null
        }
    },

    data() {
        return {
        };
    },

    computed: {
        message() {
            return this.searchAndReplace();
        }
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

            const regExp = new RegExp(this.searchTerm, 'ig');
            return this.text.replace(regExp, str => `${prefix}${str}${suffix}`);
        }
    }
});
