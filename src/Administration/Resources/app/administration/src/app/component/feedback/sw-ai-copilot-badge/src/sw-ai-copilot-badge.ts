import template from './sw-ai-copilot-badge.html.twig';
import './sw-ai-copilot-badge.scss';
import { wrapComponentConfig } from '../dependencies';

/**
 * @sw-package framework
 * @private
 */
export default wrapComponentConfig({
    template,

    props: {
        label: {
            type: Boolean,
            required: false,
            default: true,
        },
    },
});
