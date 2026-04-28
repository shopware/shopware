import template from './sw-mail-template-preview-modal.html.twig';
import './sw-mail-template-preview-modal.scss';
import previewSanitizeConfig from './mail-preview-sanitize.config';

/**
 * @sw-package after-sales
 *
 * @private
 */
export default {
    template,

    data() {
        return {
            previewSanitizeConfig,
        };
    },

    emits: [
        'modal-close',
    ],

    props: {
        mailPreview: {
            type: Object,
            required: true,
        },

        isLoading: {
            type: Boolean,
            required: false,
            default: false,
        },
    },
};
