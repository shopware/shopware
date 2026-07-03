/**
 * @sw-package inventory
 */

import './store';
import template from './sw-seo-url.html.twig';
import './sw-seo-url.scss';

const Criteria = Shopware.Data.Criteria;
const EntityCollection = Shopware.Data.EntityCollection;
const { Defaults } = Shopware;

// Mirrors the core headless template validation (SeoActionController::isFullUrlTemplate).
const FULL_URL_PATTERN = /^https?:\/\/.+/i;

/**
 * Sequences that are not URL-allowed inside a SEO path: a `%` that is not
 * part of a valid percent-escape, the fragment marker `#`, backslashes and
 * ASCII control characters. Query strings (`?`) and valid `%XX` escapes are
 * allowed. Keep this regex in sync with
 * `Shopware\\Core\\Content\\Seo\\Validation\\Constraint\\ValidSeoPathInfo::DISALLOWED_CHARACTERS_PATTERN`.
 */
// eslint-disable-next-line no-control-regex
const DISALLOWED_SEO_PATH_CHARS = /%(?![0-9A-Fa-f]{2})|[#\\\x00-\x1F\x7F]/;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'repositoryFactory',
        'seoUrlService',
    ],

    emits: ['on-change-sales-channel'],

    mixins: [],

    props: {
        salesChannelId: {
            type: String,
            required: false,
            default: null,
        },

        entity: {
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
            // Store-api route config (route name + generated base path info) for this entity, resolved on
            // demand for headless sales channels. Null while unknown or when no store-api equivalent exists.
            storeApiConfig: null,
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

            return this.currentSalesChannelId !== null && salesChannel?.typeId === Defaults.apiSalesChannelTypeId;
        },

        isUnsupportedSalesChannel() {
            if (!Shopware.Store.get('swSeoUrl')) {
                return true;
            }

            if (Shopware.Store.get('swSeoUrl').salesChannelCollection === null) {
                return true;
            }

            const salesChannel = Shopware.Store.get('swSeoUrl').salesChannelCollection.find((entry) => {
                return entry.id === this.currentSalesChannelId;
            });

            // Product comparison and agentic commerce sales channels do not serve SEO URLs.
            const unsupportedTypeIds = [
                Defaults.productComparisonTypeId,
                Defaults.agenticCommerceTypeId,
            ];

            return this.currentSalesChannelId !== null && unsupportedTypeIds.includes(salesChannel?.typeId);
        },

        seoUrlHelptext() {
            return this.isUnsupportedSalesChannel ? this.$t('sw-seo-url.textSeoUrlsNotSupported') : null;
        },

        seoPathInfoError() {
            const seoPathInfo = this.currentSeoUrl?.seoPathInfo;
            const trimmed = typeof seoPathInfo === 'string' ? seoPathInfo.trim() : '';

            if (this.isHeadlessSalesChannel && trimmed !== '' && !FULL_URL_PATTERN.test(trimmed)) {
                return { detail: this.$t('sw-seo-url-template-card.general.invalidHeadlessUrlTemplate') };
            }

            if (typeof seoPathInfo === 'string' && seoPathInfo !== '' && DISALLOWED_SEO_PATH_CHARS.test(seoPathInfo)) {
                return {
                    code: 'CONTENT__SEO_URL_INVALID_CHARACTERS',
                    detail: this.$t('sw-seo-url.errorInvalidCharacters'),
                };
            }

            return null;
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

                // The initially selected sales channel may already be a headless one; resolve its store-api
                // config once the sales channels are known so the current SEO URL targets the store-api route.
                if (this.isHeadlessSalesChannel) {
                    this.resolveStoreApiConfig().then(() => this.refreshCurrentSeoUrl());
                }
            });
        },

        resolveStoreApiConfig() {
            // Store-api route configs only apply to headless sales channels and need the entity type plus a
            // foreign key to resolve the generated base path info of the matching route.
            if (!this.isHeadlessSalesChannel || !this.entity) {
                this.storeApiConfig = null;
                return Promise.resolve();
            }

            const foreignKey =
                this.defaultSeoUrl?.foreignKey ?? this.seoUrlCollection.find((item) => item.foreignKey)?.foreignKey;
            if (!foreignKey) {
                this.storeApiConfig = null;
                return Promise.resolve();
            }

            return this.seoUrlService.getStoreApiConfigs(foreignKey).then((configs) => {
                this.storeApiConfig = configs.find((config) => config.entityName === this.entity) ?? null;
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

                let routeName = this.defaultSeoUrl?.routeName ?? seoUrl.routeName;
                let pathInfo = this.defaultSeoUrl?.pathInfo ?? seoUrl.pathInfo;

                if (this.isHeadlessSalesChannel && this.storeApiConfig) {
                    routeName = this.storeApiConfig.routeName;
                    pathInfo = this.storeApiConfig.pathInfo;
                }

                entity.foreignKey = this.defaultSeoUrl?.foreignKey ?? seoUrl.foreignKey;
                entity.isCanonical = true;
                entity.languageId = actualLanguageId;
                entity.salesChannelId = this.currentSalesChannelId;
                entity.routeName = routeName;
                entity.pathInfo = pathInfo;
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
            this.resolveStoreApiConfig().then(() => this.refreshCurrentSeoUrl());
        },
    },
};
