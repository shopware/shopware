import template from './sw-ai-copilot-warning.html.twig';
import './sw-ai-copilot-warning.scss';
import { wrapComponentConfig } from '../dependencies';

/**
 * @sw-package framework
 * @private
 */
export default wrapComponentConfig({
    template,

    props: {
        text: {
            type: String,
            required: false,
            default: '',
        },
    },

    computed: {
        warningText(): string {
            return this.text || this.$t('sw-ai-copilot-warning.text');
        },
    },
});
