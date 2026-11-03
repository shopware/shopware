import template from './sw-settings-country-preview-template.html.twig';
import './sw-settings-country-preview-template.scss';
import { wrapComponentConfig } from '../dependencies';

/**
 * @sw-package fundamentals@discovery
 *
 * @public
 */
export default wrapComponentConfig({
    template,

    props: {
        formattingAddress: {
            type: String,
            required: true,
        },

        isLoading: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    computed: {
        displayFormattingAddress(): string {
            return this.formattingAddress;
        },
    },
});
