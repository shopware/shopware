import template from './sw-extension-listing-card.html.twig';
import './sw-extension-listing-card.scss';

const { Utils, Filter } = Shopware;

const { Component } = Shopware;

/**
 * @private
 */
Component.register('sw-extension-listing-card', {
    template,

    inject: [
        'shopwareExtensionService'
    ],

    props: {
        extension: {
            type: Object,
            required: true
        }
    },

    computed: {
        previewMedia() {
            const image = Utils.get(this.extension, 'images[0]', null);

            if (!image) {
                return {
                    'background-image': `url('${this.assetFilter('/administration/static/img/theme/default_theme_preview.jpg')}')`
                };
            }

            return {
                'background-image': `url('${image.remoteLink}')`,
                'background-size': 'cover'
            };
        },

        recommendedVariant() {
            return this.shopwareExtensionService.orderVariantsByRecommendation(this.extension.variants)[0];
        },

        hasActiveDiscount() {
            return this.shopwareExtensionService.isVariantDiscounted(this.recommendedVariant);
        },

        discountClass() {
            return {
                'sw-extension-listing-card__info-price-discounted': this.hasActiveDiscount
            };
        },

        calculatedPrice() {
            if (!this.recommendedVariant) {
                return null;
            }

            return this.$tc(
                'sw-extension-store.general.labelPrice',
                this.shopwareExtensionService.mapVariantToRecommendation(this.recommendedVariant),
                {
                    price: Utils.format.currency(
                        this.shopwareExtensionService.getPriceFromVariant(this.recommendedVariant), 'EUR'
                    )
                }
            );
        },

        isInstalled() {
            return !!Shopware.State.get('shopwareExtensions').myExtensions.data.some((installedExtension) => {
                return installedExtension.installedAt && installedExtension.name === this.extension.name;
            });
        },

        isLicensed() {
            const extension = Shopware.State.get('shopwareExtensions').myExtensions.data.find((installedExtension) => installedExtension.name === this.extension.name);

            if (extension === undefined) {
                return false;
            }

            return !!extension.storeLicense;
        },

        assetFilter() {
            return Filter.getByName('asset');
        }
    },

    methods: {
        openDetailPage() {
            this.$router.push({
                name: 'sw.extension.store.detail',
                params: { id: this.extension.id.toString() }
            });
        }
    }
});
