import template from './sw-settings-system-health-text.html.twig';
import './sw-settings-system-health-text.scss';

// a URL, or a constant such as APP_URL; a sentence's final full stop is not part of a URL
const TOKEN = /(https?:\/\/[^\s,]+[^\s,.]|\b[A-Z][A-Z0-9]*(?:_[A-Z0-9]+)+\b)/g;

/**
 * Plain text with its URLs and constants (APP_URL) set apart as code.
 *
 * @sw-package framework
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    props: {
        text: {
            type: String,
            required: true,
        },
    },

    computed: {
        segments(): Array<{ text: string; isCode: boolean }> {
            return this.text
                .split(TOKEN)
                .filter((part) => part !== '')
                .map((part) => ({ text: part, isCode: new RegExp(`^${TOKEN.source}$`).test(part) }));
        },
    },
});
