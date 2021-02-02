import template from './sw-extension-store-detail.html.twig';
import './sw-extension-store-detail.scss';

const { Component, Utils } = Shopware;

/**
 * @private
 */
Component.register('sw-extension-store-detail', {
    template,

    inject: [
        'extensionStoreDataService',
        'shopwareExtensionService'
    ],

    mixins: ['sw-extension-error'],

    props: {
        id: {
            type: String,
            required: true
        }
    },

    data() {
        return {
            extension: null,
            isLoading: false,
            showBuyModal: false,
            showPermissionsModal: false,
            showAcceptPermissionsModal: false,
            isInstalling: false,
            isInstallSuccessful: false,
            permissionsAccepted: false,
            isDescriptionCollapsed: false
        };
    },

    computed: {
        suspended() {
            return this.extension === null;
        },

        license() {
            if (this.extension === null) {
                return null;
            }

            const extension = Shopware.State.get('shopwareExtensions').myExtensions.data.find((ext) => {
                return ext.id === this.extension.id;
            });

            if (extension === undefined) {
                return null;
            }

            return extension.storeLicense ? extension.storeLicense : null;
        },

        isLicensed() {
            return !!this.license;
        },

        isInstalled() {
            return Shopware.State.get('shopwareExtensions').myExtensions.data.some((extension) => {
                return extension.name === this.extension.name;
            });
        },

        images() {
            if (this.suspended) {
                return [];
            }

            return this.extension.images.map((image) => {
                return image.remoteLink;
            });
        },

        extensionCategoryNames() {
            if (this.suspended) {
                return '';
            }

            return this.extension.categories.map((category) => category.details.name).join(', ');
        },

        extensionLanguages() {
            if (this.suspended) {
                return '';
            }

            return this.extension.languages.map((language) => language).join(', ');
        },

        isPurchasable() {
            if (this.suspended) {
                return false;
            }

            return !this.isLicensed;
        },

        languageId() {
            return Shopware.State.get('session').languageId;
        },

        canBeOpened() {
            return this.shopwareExtensionService.canBeOpened(this.extension);
        },

        recommendedVariant() {
            return this.shopwareExtensionService.orderVariantsByRecommendation(this.extension.variants)[0];
        },

        dateFilter() {
            return Shopware.Filter.getByName('date');
        },

        hasActiveDiscount() {
            return this.shopwareExtensionService.isVariantDiscounted(this.recommendedVariant);
        },

        discountAppliesForMonths() {
            if (!this.hasActiveDiscount) {
                return null;
            }

            return this.recommendedVariant.discountCampaign.discountAppliesForMonths;
        },

        discountClass() {
            return {
                'sw-extension-store-detail__discounted-price': this.hasActiveDiscount
            };
        },

        calculatedPrice() {
            if (!this.recommendedVariant) {
                return null;
            }

            const label = this.hasActiveDiscount ? 'labelDiscountedPrice' : 'labelPrice';
            const discountedPrice = this.shopwareExtensionService.getPriceFromVariant(this.recommendedVariant);
            const text = this.$tc(
                `sw-extension-store.general.${label}`,
                this.shopwareExtensionService.mapVariantToRecommendation(this.recommendedVariant),
                {
                    price: Utils.format.currency(this.recommendedVariant.netPrice, 'EUR'),
                    discountedPrice: this.recommendedVariant.netPrice !== discountedPrice ?
                        Utils.format.currency(discountedPrice, 'EUR') : null
                }
            );

            return this.discountAppliesForMonths ? `${text}*` : text;
        },

        variantClass() {
            return {
                'is--theme': this.extension.isTheme
            };
        },

        orderedBinaries() {
            return Utils.get(this.extension, 'binaries', []).slice().reverse();
        },

        description() {
            return Utils.get(this.extension, 'description');
        },

        hasPermissions() {
            return Object.keys(this.extension.permissions).length;
        }
    },

    watch: {
        id: {
            immediate: true,
            handler() {
                this.fetchExtension();
            }
        },

        '$route.hash'() {
            this.scrollToElementFromHash();
        },

        suspended() {
            if (!this.suspended) {
                // wait for all child components to be mounted
                this.$nextTick(() => {
                    this.scrollToElementFromHash();
                });
            }
        },

        languageId(newValue) {
            if (newValue !== '') {
                this.fetchExtension();
            }
        },

        description() {
            this.isDescriptionCollapsed = true;
            this.$nextTick(() => {
                this.checkDescriptionCollapsed();
            });
        }
    },

    methods: {
        async fetchExtension() {
            this.isLoading = true;

            if (this.languageId === '') {
                return;
            }

            try {
                this.extension = await this.extensionStoreDataService.getDetail(
                    this.id,
                    { ...Shopware.Context.api, languageId: this.languageId }
                );
            } catch (e) {
                this.showExtensionErrors(e);
            } finally {
                this.isLoading = false;
            }
        },

        async fetchExtensionAndScrollToRatings() {
            await this.fetchExtension();
            this.$router.push({ hash: '#ratings-card' });
        },

        scrollToElementFromHash() {
            if (!this.$route.hash) {
                return;
            }

            const elementWithHashId = this.$el.querySelector(this.$route.hash);
            const pageBody = document.querySelector('div.sw-meteor-page__body');
            const header = document.querySelector('header.head-area');

            const topOfElement = elementWithHashId.offsetTop - header.getBoundingClientRect().height;

            pageBody.scroll({ top: topOfElement, behavior: 'smooth' });

            // clear hash so user can click link again
            this.$router.push(Object.assign({}, this.route, { hash: null }));
        },

        openBuyModal() {
            this.showBuyModal = true;
        },

        closeBuyModal() {
            this.showBuyModal = false;
        },

        openPermissionsModal() {
            this.showPermissionsModal = true;
        },

        closePermissionsModal() {
            this.showPermissionsModal = false;
        },

        openAcceptPermissionsModal() {
            this.showAcceptPermissionsModal = true;
        },

        async closeAcceptPermissionsModal() {
            this.showAcceptPermissionsModal = false;
        },

        async closePermissionsModalAndInstallExtension() {
            this.permissionsAccepted = true;
            this.closeAcceptPermissionsModal();
            await this.installExtension();
        },

        async handleInstallWithPermissionsModal() {
            if (Object.keys(this.extension.permissions).length) {
                this.openAcceptPermissionsModal();

                return;
            }

            this.permissionsAccepted = true;
            await this.installExtension();
        },

        async installExtension() {
            this.isInstalling = true;

            try {
                await this.shopwareExtensionService.installExtension(this.extension.name, this.permissionsAccepted);

                this.isInstallSuccessful = true;
            } catch (e) {
                this.showExtensionErrors(e);
            } finally {
                this.isInstalling = false;
            }
        },

        async openExtension() {
            const openLink = await this.shopwareExtensionService.getOpenLink(this.extension);

            if (openLink) {
                this.$router.push(openLink);
            }
        },

        checkDescriptionCollapsed() {
            const description = this.$el.querySelector('.sw-extension-store-detail__description');

            if (description && description.scrollHeight <= 300) {
                this.expandDescription();
            }
        },

        expandDescription() {
            this.isDescriptionCollapsed = false;
        }
    }
});
