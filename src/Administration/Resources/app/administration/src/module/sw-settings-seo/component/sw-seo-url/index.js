/**
 * @sw-package inventory
 */

import './store';
import template from './sw-seo-url.html.twig';
import './sw-seo-url.scss';
import { EventBus } from 'shopware:utils';
import useSwSeoUrlStore from 'shopware:stores/swSeoUrl';

const Criteria = Shopware.Data.Criteria;
const EntityCollection = Shopware.Data.EntityCollection;
const { Defaults } = Shopware;

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
            return useSwSeoUrlStore().seoUrlCollection;
        },

        currentSeoUrl() {
            if (!useSwSeoUrlStore()) {
                return {};
            }

            return useSwSeoUrlStore().currentSeoUrl;
        },

        defaultSeoUrl() {
            return useSwSeoUrlStore().defaultSeoUrl;
        },

        seoUrlRepository() {
            return this.repositoryFactory.create('seo_url');
        },

        salesChannelRepository() {
            return this.repositoryFactory.create('sales_channel');
        },

        isUnsupportedSalesChannel() {
            if (!useSwSeoUrlStore()) {
                return true;
            }

            if (useSwSeoUrlStore().salesChannelCollection === null) {
                return true;
            }

            const salesChannel = useSwSeoUrlStore().salesChannelCollection.find((entry) => {
                return entry.id === this.currentSalesChannelId;
            });

            // Product comparison and agentic commerce sales channels do not serve SEO URLs.
            const unsupportedTypeIds = [
                Defaults.productComparisonTypeId,
                Defaults.agenticCommerceTypeId,
            ];

            return this.currentSalesChannelId !== null && unsupportedTypeIds.includes(salesChannel?.typeId);
        },

        currentSalesChannel() {
            const salesChannelCollection = useSwSeoUrlStore()?.salesChannelCollection;

            return salesChannelCollection?.find((entry) => entry.id === this.currentSalesChannelId) ?? null;
        },

        currentSalesChannelIsHeadless() {
            return this.currentSalesChannel?.typeId === Defaults.apiSalesChannelTypeId;
        },

        headlessExternalStorefrontUrl() {
            const url = this.currentSalesChannel?.domains?.find(
                (domain) => domain.isExternalStorefront && domain.languageId === Shopware.Context.api.languageId,
            )?.url;

            if (!url || url.endsWith('/')) {
                return url ?? null;
            }

            return `${url}/`;
        },

        seoUrlHelptext() {
            if (this.isUnsupportedSalesChannel) {
                return this.$t('sw-seo-url.textSeoUrlsNotSupported');
            }

            if (this.currentSalesChannelIsHeadless && !this.headlessExternalStorefrontUrl) {
                return this.$t('sw-seo-url-template-card.general.textExternalStorefrontRequired');
            }

            return null;
        },

        seoPathInfoError() {
            const seoPathInfo = this.currentSeoUrl?.seoPathInfo;

            if (typeof seoPathInfo !== 'string' || seoPathInfo === '') {
                return null;
            }

            if (!DISALLOWED_SEO_PATH_CHARS.test(seoPathInfo)) {
                return null;
            }

            return {
                code: 'CONTENT__SEO_URL_INVALID_CHARACTERS',
                detail: this.$t('sw-seo-url.errorInvalidCharacters'),
            };
        },

        hasAdditionalSeoSlot() {
            return this.$slots.hasOwnProperty('seo-additional');
        },

        allowInput() {
            return (
                (this.hasDefaultTemplate || this.currentSalesChannelId !== null) &&
                (!this.currentSalesChannelIsHeadless || !!this.headlessExternalStorefrontUrl)
            );
        },
    },

    watch: {
        urls() {
            this.initSeoUrlCollection();
            this.refreshCurrentSeoUrl();
        },
    },

    created() {
        EventBus.on('sw-product-detail-save-finish', this.clearDefaultSeoUrls);

        this.createdComponent();
    },

    beforeUnmount() {
        EventBus.off('sw-product-detail-save-finish', this.clearDefaultSeoUrls);
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
            salesChannelCriteria.addAssociation('domains');

            this.salesChannelRepository.search(salesChannelCriteria).then((salesChannelCollection) => {
                useSwSeoUrlStore().salesChannelCollection = salesChannelCollection;
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
            useSwSeoUrlStore().defaultSeoUrl = defaultSeoUrlEntity;

            this.urls.forEach((entityData) => {
                const entity = this.seoUrlRepository.create();
                Object.assign(entity, entityData);

                seoUrlCollection.add(entity);
            });

            if (!useSwSeoUrlStore().defaultSeoUrl) {
                this.showEmptySeoUrlError = true;
            }

            useSwSeoUrlStore().seoUrlCollection = seoUrlCollection;
            useSwSeoUrlStore().originalSeoUrls = this.urls;
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

                useSwSeoUrlStore().currentSeoUrl = entity;

                return;
            }

            useSwSeoUrlStore().currentSeoUrl = currentSeoUrl;
        },

        onSalesChannelChanged(salesChannelId) {
            this.currentSalesChannelId = salesChannelId;
            this.$emit('on-change-sales-channel', salesChannelId);
            this.refreshCurrentSeoUrl();
        },
    },
};
