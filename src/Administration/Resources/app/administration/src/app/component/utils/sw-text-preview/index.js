import './sw-text-preview.scss';
import template from './sw-text-preview.html.twig';

const { Component } = Shopware;

/**
 * @deprecated tag:v6.6.0 - Will be private
 * @public
 * @description Displays text (no html) up to a defined length and shows a "Show more" button that opens a modal.
 *              New lines are converted into line-breaks (br) and empty lines are removed in preview.
 * @status ready
 * @example-type dynamic
 * @component-example
 * <sw-text-preview :text="comment" modal-title="Comment" :maximum-length="750" :maximum-new-lines="5"></sw-text-preview>
 */
const lineExpr = /(?:\r\n|\r|\n)/g;
const lineBreak = '<br />';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-text-preview', {
    template,

    props: {
        text: {
            type: String,
            required: true,
        },
        maximumLength: {
            type: Number,
            required: true,
        },
        modalTitle: {
            type: String,
            required: false,
            default: '',
        },
        maximumNewLines: {
            type: Number,
            required: false,
            default: 0,
        },
    },

    data() {
        return {
            shortened: false,
            showModal: false,
        };
    },

    computed: {
        shortenedText() {
            let text = this.text;
            // eslint-disable-next-line vue/no-side-effects-in-computed-properties
            this.shortened = false;
            if (this.maximumNewLines > 0) {
                const splitted = text.split(lineExpr).filter((element) => {
                    return !!element.trim();
                });
                if (splitted.length > this.maximumNewLines) {
                    text = splitted.slice(0, this.maximumNewLines).join('\n');
                    // eslint-disable-next-line vue/no-side-effects-in-computed-properties
                    this.shortened = true;
                }
            }
            if (text.length > this.maximumLength) {
                // eslint-disable-next-line vue/no-side-effects-in-computed-properties
                this.shortened = true;
            }
            return text.slice(0, this.maximumLength).replace(lineExpr, lineBreak);
        },
        fullText() {
            return this.text.replace(lineExpr, lineBreak);
        },
    },

    methods: {
        closeModal() {
            this.showModal = false;
        },

        openModal() {
            this.showModal = true;
        },
    },
});
