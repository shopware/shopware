import template from './sw-extension-buy-modal.html.twig';
import './sw-extension-buy-modal.scss';

const { Component, Utils } = Shopware;

/**
 * @private
 */
Component.register('sw-extension-buy-modal', {
    template,

    inject: [
        'shopwareExtensionService'
    ],

    mixins: [
        'sw-extension-error'
    ],

    props: {
        extension: {
            type: Object,
            required: true
        }
    },

    data() {
        return {
            tocAccepted: false,
            selectedVariantId: null,
            isLoading: false,
            permissionsAccepted: false,
            showPermissionsModal: false,
            privacyExtensionsAccepted: false,
            showPrivacyModal: false,
            checkoutStep: null
        };
    },

    computed: {
        recommendedVariants() {
            return this.shopwareExtensionService.orderVariantsByRecommendation(
                this.extension.variants
            ).filter((variant) => {
                return Object.values(this.shopwareExtensionService.EXTENSION_VARIANT_TYPES).includes(variant.type);
            });
        },

        selectedVariant() {
            return this.extension.variants.find((variant) => {
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
            return Shopware.State.get('shopwareExtensions').loginStatus;
        },

        checkoutSteps() {
            return Object.freeze({
                CHECKOUT: null,
                SUCCESS: 'checkout-success',
                FAILED: 'checkout-failed'
            });
        }
    },

    created() {
        const variantId = this.recommendedVariants.length > 0
            ? this.recommendedVariants[0].id
            : this.extension.variants[0].id;

        this.setSelectedVariantId(variantId);
        this.permissionsAccepted = !this.extensionHasPermissions;
        this.privacyExtensionsAccepted = !this.extension.privacyPolicyExtension;
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

            let checkoutResult = null;

            try {
                await this.shopwareExtensionService.purchaseExtension(
                    this.extension.id,
                    this.selectedVariant.id,
                    this.tocAccepted,
                    this.permissionsAccepted
                );
                checkoutResult = this.checkoutSteps.SUCCESS;
            } catch (e) {
                this.handleErrors(e);
                checkoutResult = this.checkoutSteps.FAILED;
            } finally {
                await this.shopwareExtensionService.updateExtensionData();
                this.checkoutStep = checkoutResult;

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

            this.showExtensionErrors(error);
        },

        openPermissionsModal() {
            this.showPermissionsModal = true;
        },

        closePermissionsModal() {
            this.showPermissionsModal = false;
        },

        async fetchPlan() {
            this.isLoading = true;
            await this.shopwareExtensionService.checkLogin();
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
