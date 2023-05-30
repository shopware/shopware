import template from './sw-media-url-form.html.twig';

/**
 * @status ready
 * @description The <u>sw-media-url-form</u> component is used to validate urls from the user.
 * @package content
 * @example-type static
 * @component-example
 * <sw-media-url-form variant="inline">
 * </sw-media-url-form>
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    props: {
        variant: {
            type: String,
            required: true,
            validValues: ['modal', 'inline'],
            validator(value) {
                return ['modal', 'inline'].includes(value);
            },
            default: 'inline',
        },
    },

    data() {
        return {
            url: '',
            extensionFromUrl: '',
            extensionFromInput: '',
        };
    },

    computed: {
        urlObject() {
            try {
                return new URL(this.url);
            } catch (e) {
                // eslint-disable-next-line vue/no-side-effects-in-computed-properties
                this.extensionFromUrl = '';
                return null;
            }
        },

        hasInvalidInput() {
            return this.urlObject === null && this.url !== '';
        },

        invalidUrlError() {
            if (this.hasInvalidInput) {
                return { code: 'INVALID_MEDIA_URL' };
            }

            return null;
        },

        missingFileExtension() {
            return this.urlObject !== null && !this.extensionFromUrl;
        },

        fileExtension() {
            return this.extensionFromUrl || this.extensionFromInput;
        },

        isValid() {
            return this.urlObject !== null && this.fileExtension;
        },
    },

    watch: {
        urlObject() {
            if (this.urlObject === null) {
                this.extensionFromUrl = '';
                return;
            }

            const fileName = this.urlObject.pathname.split('/').pop();
            if (fileName.split('.').length === 1) {
                this.extensionFromUrl = '';
                return;
            }

            this.extensionFromUrl = fileName.split('.').pop();
        },
    },

    methods: {
        emitUrl(originalDomEvent) {
            if (this.isValid) {
                this.$emit('media-url-form-submit', {
                    originalDomEvent,
                    url: this.urlObject,
                    fileExtension: this.fileExtension,
                });

                if (this.variant === 'modal') {
                    this.closeModal();
                }
            }
        },

        closeModal() {
            this.$emit('modal-close');
        },
    },
};
