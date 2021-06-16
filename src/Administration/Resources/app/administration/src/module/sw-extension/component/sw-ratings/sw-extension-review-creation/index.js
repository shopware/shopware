import template from './sw-extension-review-creation.html.twig';
import './sw-extension-review-creation.scss';

const { ShopwareError } = Shopware.Classes;
const { Component } = Shopware;

/**
 * @private
 */
Component.register('sw-extension-review-creation', {
    template,

    inject: ['extensionStoreActionService'],

    mixins: ['sw-extension-error'],

    props: {
        extension: {
            type: Object,
            required: true,
        },
    },

    data() {
        return {
            tocAccepted: false,
            isLoading: false,
            isCreatedSuccessful: false,
            headline: null,
            rating: null,
            text: null,
            errors: {
                headlineError: null,
                ratingError: null,
            },
        };
    },

    computed: {
        currentUser() {
            return Shopware.State.get('session').currentUser;
        },

        userName() {
            if (!this.currentUser) {
                return '';
            }

            return `${this.currentUser.firstName} ${this.currentUser.lastName}`.trim();
        },

        installedVersion() {
            const installedExtension = Shopware.State.get('shopwareExtensions').myExtensions.data.find(
                (extension) => extension.name === this.extension.name,
            );

            return installedExtension.version;
        },

        hasError() {
            return this.errors.headlineError !== null
                || this.errors.ratingError !== null;
        },

        disabled() {
            return this.hasError || !this.tocAccepted;
        },
    },

    watch: {
        headline() {
            this.validateHeadline();
        },

        rating() {
            this.validateRating();
        },
    },

    methods: {
        async handleCreateReview() {
            this.isLoading = true;
            this.validateInputs();

            if (this.hasError) {
                this.isLoading = false;
                return;
            }

            const review = {
                extensionId: this.extension.id,
                authorName: this.userName,
                headline: this.headline,
                rating: this.rating,
                text: this.text,
                tocAccepted: this.tocAccepted,
                version: this.installedVersion,
            };

            await this.createReview(review);
            this.isLoading = false;
        },

        async createReview(review) {
            try {
                await this.extensionStoreActionService.rateExtension(review);
                this.isCreatedSuccessful = true;
            } catch (e) {
                this.showExtensionErrors(e);
            }
        },

        clearData() {
            this.tocAccepted = false;
            this.headline = null;
            this.rating = null;
            this.text = null;

            this.$nextTick(() => {
                this.errors.headlineError = null;
                this.errors.ratingError = null;
            });
        },

        validateInputs() {
            this.validateHeadline();
            this.validateRating();
        },

        validateHeadline() {
            if (this.headline === null || this.headline === '') {
                this.errors.headlineError = new ShopwareError({
                    code: 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                });

                return;
            }

            this.errors.headlineError = null;
        },

        validateRating() {
            if (this.rating === null || this.rating === 0) {
                this.errors.ratingError = new ShopwareError({
                    code: 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                });

                return;
            }

            this.errors.ratingError = null;
        },

        onChange(type, value) {
            this[type] = value;
        },

        emitCreated() {
            this.$emit('created');
            this.isCreatedSuccessful = false;
        },
    },
});
