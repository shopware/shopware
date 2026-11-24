import template from './sw-settings-system-health-request.html.twig';
import './sw-settings-system-health-request.scss';

/**
 * The result of one HTTP request a check made, on one line: response code, URL, response time, and the error
 * underneath when there is one. Several requests are a stack of these lines.
 *
 * @sw-package framework
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    props: {
        // what was requested; without a URL the label names it (for example "Administration start page")
        url: {
            type: String,
            required: false,
            default: null,
        },

        label: {
            type: String,
            required: false,
            default: null,
        },

        // null when no response came back
        statusCode: {
            type: Number,
            required: false,
            default: null,
        },

        durationMs: {
            type: Number,
            required: false,
            default: null,
        },

        // when the request was made, shown on the right instead of a duration
        time: {
            type: String,
            required: false,
            default: null,
        },

        error: {
            type: String,
            required: false,
            default: null,
        },

        // the outcome when the check knows it but not the response code
        successful: {
            type: Boolean,
            required: false,
            default: null,
        },
    },
});
