/**
 * @sw-package inventory
 */

import './store';
import template from './sw-seo-url.html.twig';

const Criteria = Shopware.Data.Criteria;
const EntityCollection = Shopware.Data.EntityCollection;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory'],

    emits: ['on-change-sales-channel'],

    mixins: [],

    props: {
        salesChannelId: {
            type: String,
            required: false,
            default: null,
        },

        urls: {
            type: Array,
            required: false,
            default() {
                return [];
            },
        },

        isLoading: {
            type: Boolean,
            required: false,
            default: false,
        },

        hasDefaultTemplate: {
            type: Boolean,
            required: false,
            // eslint-disable-next-line vue/no-boolean-default
            default: true,
        },

        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },

        resultLimit: {
            type: Number,
            required: false,
            default: 25,
        },
    },

    data() {
        return {
            currentSalesChannelId: this.salesChannelId,
            showEmptySeoUrlError: false,
        };
    },

    computed: {
        seoUrlCollection() {
            return Shopware.Store.get('swSeoUrl').seoUrlCollection;
        },

        currentSeoUrl() {
            if (!Shopware.Store.get('swSeoUrl')) {
                return {};
            }

            return Shopware.Store.get('swSeoUrl').currentSeoUrl;
        },

        defaultSeoUrl() {
            return Shopware.Store.get('swSeoUrl').defaultSeoUrl;
        },

        seoUrlRepository() {
            return this.repositoryFactory.create('seo_url');
        },

        salesChannelRepository() {
            return this.repositoryFactory.create('sales_channel');
        },

        isHeadlessSalesChannel() {
            if (!Shopware.Store.get('swSeoUrl')) {
                return true;
            }

            if (Shopware.Store.get('swSeoUrl').salesChannelCollection === null) {
                return true;
            }

            const salesChannel = Shopware.Store.get('swSeoUrl').salesChannelCollection.find((entry) => {
                return entry.id === this.currentSalesChannelId;
            });

            // from Defaults.php
            return this.currentSalesChannelId !== null && salesChannel?.typeId === 'f183ee5650cf4bdb8a774337575067a6';
        },

        seoUrlHelptext() {
            return this.isHeadlessSalesChannel ? this.$tc('sw-seo-url.textSeoUrlsDisallowedForHeadless') : null;
        },

        hasAdditionalSeoSlot() {
            return this.$slots.hasOwnProperty('seo-additional');
        },

        allowInput() {
            return this.hasDefaultTemplate || this.currentSalesChannelId !== null;
        },
    },

    watch: {
        urls() {
            this.initSeoUrlCollection();
            this.refreshCurrentSeoUrl();
        },
    },

    created() {
        Shopware.Utils.EventBus.on('sw-product-detail-save-finish', this.clearDefaultSeoUrls);

        this.createdComponent();
    },

    beforeUnmount() {
        Shopware.Utils.EventBus.off('sw-product-detail-save-finish', this.clearDefaultSeoUrls);
    },

    methods: {
        createdComponent() {
            this.initSalesChannelCollection();
            this.initSeoUrlCollection();
            if (!this.showEmptySeoUrlError) {
                this.refreshCurrentSeoUrl();
            }
        },

        initSalesChannelCollection() {
            const salesChannelCriteria = new Criteria(1, this.resultLimit);
            salesChannelCriteria.addAssociation('type');

            this.salesChannelRepository.search(salesChannelCriteria).then((salesChannelCollection) => {
                Shopware.Store.get('swSeoUrl').salesChannelCollection = salesChannelCollection;
            });
        },

        initSeoUrlCollection() {
            this.showEmptySeoUrlError = false;
            const seoUrlCollection = new EntityCollection(
                this.seoUrlRepository.route,
                this.seoUrlRepository.schema.entity,
                Shopware.Context.api,
                new Criteria(1, this.resultLimit),
            );

            const defaultSeoUrlData = this.urls.find((entityData) => {
                return entityData.salesChannelId === null;
            });

            if (defaultSeoUrlData === undefined && (this.hasDefaultTemplate || this.urls.length <= 0)) {
                this.showEmptySeoUrlError = true;
            }

            const defaultSeoUrlEntity = this.seoUrlRepository.create();
            Object.assign(defaultSeoUrlEntity, defaultSeoUrlData);
            seoUrlCollection.add(defaultSeoUrlEntity);
            Shopware.Store.get('swSeoUrl').defaultSeoUrl = defaultSeoUrlEntity;

            this.urls.forEach((entityData) => {
                const entity = this.seoUrlRepository.create();
                Object.assign(entity, entityData);

                seoUrlCollection.add(entity);
            });

            if (!Shopware.Store.get('swSeoUrl').defaultSeoUrl) {
                this.showEmptySeoUrlError = true;
            }

            Shopware.Store.get('swSeoUrl').seoUrlCollection = seoUrlCollection;
            Shopware.Store.get('swSeoUrl').originalSeoUrls = this.urls;
            this.clearDefaultSeoUrls();
        },

        clearDefaultSeoUrls() {
            this.seoUrlCollection.forEach((entity) => {
                if (entity.id === this.defaultSeoUrl.id) {
                    return;
                }

                if (entity.seoPathInfo === this.defaultSeoUrl.seoPathInfo) {
                    entity.seoPathInfo = null;
                }
            });
        },

        refreshCurrentSeoUrl() {
            const actualLanguageId = Shopware.Context.api.languageId;

            const currentSeoUrl = this.seoUrlCollection.find((entity) => {
                return entity.languageId === actualLanguageId && entity.salesChannelId === this.currentSalesChannelId;
            });

            if (!currentSeoUrl) {
                const entity = this.seoUrlRepository.create();
                // Fetch any seo url as template, since we need to know foreignKey, pathInfo and the routeName
                const seoUrl =
                    this.seoUrlCollection.find((item) => {
                        return item.pathInfo && item.routeName && item.foreignKey;
                    }) || {};

                entity.foreignKey = this.defaultSeoUrl?.foreignKey ?? seoUrl.foreignKey;
                entity.isCanonical = true;
                entity.languageId = actualLanguageId;
                entity.salesChannelId = this.currentSalesChannelId;
                entity.routeName = this.defaultSeoUrl?.routeName ?? seoUrl.routeName;
                entity.pathInfo = this.defaultSeoUrl?.pathInfo ?? seoUrl.pathInfo;
                entity.isModified = true;

                this.seoUrlCollection.add(entity);

                Shopware.Store.get('swSeoUrl').currentSeoUrl = entity;

                return;
            }

            Shopware.Store.get('swSeoUrl').currentSeoUrl = currentSeoUrl;
        },
        onSalesChannelChanged(salesChannelId) {
            this.currentSalesChannelId = salesChannelId;
            this.$emit('on-change-sales-channel', salesChannelId);
            this.refreshCurrentSeoUrl();
        },
    },
};
