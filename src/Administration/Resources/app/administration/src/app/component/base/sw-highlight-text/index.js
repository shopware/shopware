import { h } from 'vue';
import './sw-highlight-text.scss';

const { Context } = Shopware;

/**
 * @sw-package framework
 *
 * @private
 * @description This component highlights text based on the searchTerm using regex
 * @status ready
 * @example-type dynamic
 * @component-example
 * <sw-highlight-text text="Lorem ipsum dolor sit amet, consetetur sadipscing elitr" searchTerm="sit"></sw-highlight-text>
 */
export default {
    template: '',

    render(createElement) {
        const parts = this.getParts();

        // Vue2 syntax
        if (typeof createElement === 'function') {
            const children = parts.map((part) => {
                if (!part.highlighted) {
                    return part.text;
                }

                return createElement(
                    'span',
                    {
                        class: 'sw-highlight-text__highlight',
                    },
                    part.text,
                );
            });

            return createElement(
                'div',
                {
                    class: 'sw-highlight-text',
                },
                children,
            );
        }

        const children = parts.map((part) => {
            if (!part.highlighted) {
                return part.text;
            }

            return h('span', { class: 'sw-highlight-text__highlight' }, part.text);
        });

        // Vue3 syntax
        return h(
            'div',
            {
                class: 'sw-highlight-text',
            },
            children,
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
        getParts() {
            if (!this.text) {
                return [];
            }

            if (!this.searchTerm) {
                return [{ text: this.text, highlighted: false }];
            }

            const pattern = this.escapeRegExp(this.searchTerm).trim();

            if (!pattern) {
                return [{ text: this.text, highlighted: false }];
            }

            const regExp = new RegExp(pattern, 'ig');
            const parts = [];
            let currentIndex = 0;
            let match = regExp.exec(this.text);

            while (match) {
                if (match.index > currentIndex) {
                    parts.push({
                        text: this.text.substring(currentIndex, match.index),
                        highlighted: false,
                    });
                }

                parts.push({
                    text: match[0],
                    highlighted: true,
                });

                currentIndex = regExp.lastIndex;
                match = regExp.exec(this.text);
            }

            if (currentIndex < this.text.length) {
                parts.push({
                    text: this.text.substring(currentIndex),
                    highlighted: false,
                });
            }

            return parts;
        },

        // Remove regex special characters from search string
        escapeRegExp(string) {
            const escapeRegex = RegExp.escape ?? ((value) => value.replace(/[.*+\-?^${}()|[\]\\]/g, '\\$&'));

            if (Context.app.adminEsEnable) {
                string = string
                    .replace(/(?<!\S)[+-]|[+-](?!\S)/g, '') // remove + and - which are not part of a word
                    .replace(/[.*~"|()]/g, '') // remove special elasticsearch characters
                    .replace(/\b(AND|OR)\b/gi, ' ') // remove AND and OR bool operators
                    .replace(/\s+/g, ' '); // replace multiple spaces with single space

                return escapeRegex(string);
            }

            return escapeRegex(string);
        },
    },
};
