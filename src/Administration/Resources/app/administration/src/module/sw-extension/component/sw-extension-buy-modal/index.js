import template from './sw-extension-buy-modal.html.twig';
import './sw-extension-buy-modal.scss';

const { Component, Mixin, Utils } = Shopware;

Component.register('sw-extension-buy-modal', {
    template,

    inject: [
        'shopwareExtensionService'
    ],

    mixins: [Mixin.getByName('notification')],

    props: {
        extension: {
            type: Object,
            required: false
        }
    },

    data() {
        return {
            tocAccepted: false,
            selectedVariantId: null,
            isLoading: false,
            hasSuccessfullyInstalledExtension: false,
            permissionsAccepted: false,
            showPermissionsModal: false,
            privacyExtensionsAccepted: false,
            showPrivacyModal: false
        };
    },

    computed: {
        recommendedVariants() {
            const filteredVariants = this.extension.variants.filter((variant) => {
                return variant.type === this.shopwareExtensionService.EXTENSION_VARIANT_TYPES.FREE ||
                    variant.type === this.shopwareExtensionService.EXTENSION_VARIANT_TYPES.RENT;
            });

            return this.shopwareExtensionService.orderVariantsByRecommendation(filteredVariants);
        },

        selectedVariant() {
            return this.recommendedVariants.find((variant) => {
                return variant.id === this.selectedVariantId;
            });
        },

        todayPlusOneMonth() {
            const date = new Date();
            date.setMonth(date.getMonth() + 1);

            return date;
        },

        dateFilter() {
            return Utils.format.date;
        },

        formattedPrice() {
            return Utils.format.currency(
                this.shopwareExtensionService.getPriceFromVariant(this.selectedVariant), 'EUR'
            );
        },

        purchaseButtonLabel() {
            switch (this.selectedVariant.type) {
                case this.shopwareExtensionService.EXTENSION_VARIANT_TYPES.FREE:
                    return this.$tc('sw-extension-store.component.sw-extension-buy-modal.purchaseButtonsLabels.free');

                case this.shopwareExtensionService.EXTENSION_VARIANT_TYPES.RENT:
                    return this.$tc('sw-extension-store.component.sw-extension-buy-modal.purchaseButtonsLabels.rent');

                case this.shopwareExtensionService.EXTENSION_VARIANT_TYPES.BUY:
                default:
                    return this.$tc('sw-extension-store.component.sw-extension-buy-modal.purchaseButtonsLabels.buy');
            }
        },

        vatIncludedClasses() {
            return {
                'is--hidden': this.selectedVariant.type === this.shopwareExtensionService.EXTENSION_VARIANT_TYPES.FREE
            };
        },

        renewalDateClasses() {
            return {
                'is--hidden': this.selectedVariant.type !== this.shopwareExtensionService.EXTENSION_VARIANT_TYPES.RENT
            };
        },

        extensionHasPermissions() {
            return !!Object.keys(this.extension.permissions).length;
        },

        canPurchaseExtension() {
            return this.tocAccepted &&
                this.permissionsAccepted &&
                this.privacyExtensionsAccepted &&
                this.userCanBuyFromStore;
        },

        /* onPrem we need to check if the user is connected to the store in saas we check if the user has a plan */
        userCanBuyFromStore() {
            return Shopware.State.get('swPlugin').loginStatus;
        },

        planBookingModalLink() {
            return { query: { ...this.$route.query, showBookingModal: 1 } };
        }
    },

    created() {
        this.setSelectedVariantId(this.recommendedVariants[0].id);
        this.permissionsAccepted = !this.extensionHasPermissions;
        this.privacyExtensionsAccepted = !this.extension.privacyPolicyExtensions;
        this.fetchPlan();
    },

    methods: {
        emitClose() {
            if (this.isLoading) {
                return;
            }

            this.$emit('modal-close');
        },

        setSelectedVariantId(variantId) {
            if (this.isLoading) {
                return;
            }

            this.selectedVariantId = variantId;
        },

        variantCardClass(variant) {
            return {
                'is--selected': variant.id === this.selectedVariantId
            };
        },

        onChangeVariantSelection(variant) {
            this.setSelectedVariantId(variant.id);
        },

        variantRecommendation(variant) {
            return this.shopwareExtensionService.mapVariantToRecommendation(variant);
        },

        async purchaseExtension() {
            this.isLoading = true;

            try {
                await this.shopwareExtensionService.purchaseExtension(
                    this.extension.id,
                    this.selectedVariant.id,
                    this.tocAccepted,
                    this.permissionsAccepted
                );

                this.hasSuccessfullyInstalledExtension = true;
            } catch (e) {
                this.handleErrors(e);
            } finally {
                this.isLoading = false;
            }
        },

        variantsCardLabel(variant) {
            const price = this.shopwareExtensionService.getPriceFromVariant(variant);

            switch (variant.type) {
                case this.shopwareExtensionService.EXTENSION_VARIANT_TYPES.FREE:
                    return this.$tc(
                        'sw-extension-store.component.sw-extension-buy-modal.variantCard.free'
                    );

                case this.shopwareExtensionService.EXTENSION_VARIANT_TYPES.RENT:
                    return this.$tc(
                        'sw-extension-store.component.sw-extension-buy-modal.variantCard.rent',
                        0,
                        { price: Utils.format.currency(price, 'EUR') }
                    );

                case this.shopwareExtensionService.EXTENSION_VARIANT_TYPES.BUY:
                default:
                    return this.$tc(
                        'sw-extension-store.component.sw-extension-buy-modal.variantCard.buy',
                        0,
                        { price: Utils.format.currency(price, 'EUR') }
                    );
            }
        },

        handleErrors(error) {
            const noDefaultPayment = error.response.data.errors.find(
                (e) => e.code === 'SAAS_EXTENSION_ORDER__NO_DEFAULT_PAYMENT_MEAN'
            );

            const noCompany = error.response.data.errors.find(
                (e) => e.code === 'SAAS_COMPANY__MISSING'
            );

            if (noDefaultPayment) {
                this.createNotificationError({
                    system: true,
                    autoClose: false,
                    growl: true,
                    title: this.$tc('global.default.error'),
                    message: this.$tc('sw-extension-store.errors.SAAS_EXTENSION_ORDER__NO_DEFAULT_PAYMENT_MEAN.message'),
                    actions: [
                        {
                            label: this.$tc('sw-extension-store.errors.SAAS_EXTENSION_ORDER__NO_DEFAULT_PAYMENT_MEAN.labelLink'),
                            method: () => {
                                this.emitClose();

                                this.$nextTick(() => {
                                    this.$router.push({
                                        name: 'sw.rufus.settings.billing.index',
                                        params: { defaultTab: 'payment' }
                                    });
                                });
                            }
                        }
                    ]
                });
            }

            if (noCompany) {
                this.createNotificationError({
                    system: true,
                    autoClose: false,
                    growl: true,
                    title: this.$tc('global.default.error'),
                    message: this.$tc('sw-extension-store.errors.SAAS_COMPANY__MISSING.message'),
                    actions: [
                        {
                            label: this.$tc('sw-extension-store.errors.SAAS_COMPANY__MISSING.labelLink'),
                            method: () => {
                                this.emitClose();

                                this.$nextTick(() => {
                                    this.$router.push({
                                        name: 'sw.rufus.settings.company.index'
                                    });
                                });
                            }
                        }
                    ]
                });
            }

            error.response.data.errors = error.response.data.errors.filter(
                (e) => e.code !== 'SAAS_EXTENSION_ORDER__NO_DEFAULT_PAYMENT_MEAN' && e.code !== 'SAAS_COMPANY__MISSING'
            );

            this.showSaasErrors(error);
        },

        openPermissionsModal() {
            this.showPermissionsModal = true;
        },

        closePermissionsModal() {
            this.showPermissionsModal = false;
        },

        async fetchPlan() {
            this.isLoading = true;
            await Shopware.State.dispatch('swPlugin/checkLogin');
            this.isLoading = false;
        },

        openPrivacyModal() {
            this.showPrivacyModal = true;
        },

        closePrivacyModal() {
            this.showPrivacyModal = false;
        }
    }
});
