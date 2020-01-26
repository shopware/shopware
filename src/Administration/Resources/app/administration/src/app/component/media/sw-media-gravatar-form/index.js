import './sw-media-gravatar-form.scss';
import template from './sw-media-gravatar-form.html.twig';

const { Component } = Shopware;

/**
 * @status ready
 * @description The <u>sw-media-gravatar-form</u> component is used to preview gravatar avatars.
 * @example-type static
 * @component-example
 * <sw-media-gravatar-form variant="inline">
 * </sw-media-gravatar-form>
 */
Component.register('sw-media-gravatar-form', {
    template,

    inject: [
        'ExternalApiGravatarService'
    ],

    props: {
        variant: {
            type: String,
            required: true,
            validValues: ['modal', 'inline'],
            validator(value) {
                return ['modal', 'inline'].includes(value);
            },
            default: 'inline'
        },
        email: {
            type: String,
            required: false,
            default: null
        }
    },

    data() {
        return {
            loading: false,
            url: ''
        };
    },

    created() {
        this.loadUrl();
    },

    watch: {
        email() {
            this.loadUrl();
        }
    },

    computed: {
        isValid() {
            return !this.loading && this.url;
        }
    },

    methods: {
        loadUrl() {
            this.loading = true;
            this.ExternalApiGravatarService.requestAvatarUrl(this.email, 240)
                .then(url => {
                    this.url = url;
                    this.loading = false;
                });
        },

        emitUrl(originalDomEvent) {
            if (this.isValid) {
                this.$emit('media-gravatar-form-submit', {
                    originalDomEvent,
                    url: new URL(this.url),
                    fileExtension: 'jpg'
                });

                if (this.variant === 'modal') {
                    this.closeModal();
                }
            }
        },

        closeModal() {
            this.$emit('modal-close');
        }
    }
});
