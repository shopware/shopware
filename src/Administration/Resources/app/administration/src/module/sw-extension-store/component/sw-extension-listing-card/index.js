import template from './sw-extension-listing-card.html.twig';
import './sw-extension-listing-card.scss';

const { Utils, Filter } = Shopware;

const { Component } = Shopware;

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
                    'background-image': this.defaultThemeAsset
                };
            }

            return {
                'background-image': `url('${image.remoteLink}')`,
                'background-size': 'cover'
            };
        },

        defaultThemeAsset() {
            return `url('${this.assetFilter('/administration/static/img/theme/default_theme_preview.jpg')}')`;
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
            return !!Shopware.State.get('shopwareExtensions').installedExtensions.data.find((installedExtension) => {
                return installedExtension.name === this.extension.name;
            });
        },

        isLicensed() {
            return !!Shopware.State.get('shopwareExtensions').licensedExtensions.data.find((license) => {
                return license.licensedExtension.name === this.extension.name;
            });
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
