import swSeoUrlState from './state';
import template from './sw-seo-url.html.twig';

const { Component } = Shopware;
const Criteria = Shopware.Data.Criteria;
const EntityCollection = Shopware.Data.EntityCollection;

Component.register('sw-seo-url', {
    template,

    inject: ['repositoryFactory'],

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
    },

    data() {
        return {
            currentSalesChannelId: this.salesChannelId,
            showEmptySeoUrlError: false,
        };
    },

    computed: {
        seoUrlCollection() {
            return Shopware.State.get('swSeoUrl').seoUrlCollection;
        },

        currentSeoUrl() {
            if (!Shopware.State.get('swSeoUrl')) {
                return {};
            }

            return Shopware.State.get('swSeoUrl').currentSeoUrl;
        },

        defaultSeoUrl() {
            return Shopware.State.get('swSeoUrl').defaultSeoUrl;
        },

        seoUrlRepository() {
            return this.repositoryFactory.create('seo_url');
        },

        salesChannelRepository() {
            return this.repositoryFactory.create('sales_channel');
        },

        isHeadlessSalesChannel() {
            if (!Shopware.State.get('swSeoUrl')) {
                return true;
            }

            if (Shopware.State.get('swSeoUrl').salesChannelCollection === null) {
                return true;
            }

            const salesChannel = Shopware.State.get('swSeoUrl').salesChannelCollection.find((entry) => {
                return entry.id === this.currentSalesChannelId;
            });

            // from Defaults.php
            return this.currentSalesChannelId !== null && salesChannel.typeId === 'f183ee5650cf4bdb8a774337575067a6';
        },

        seoUrlHelptext() {
            return this.isHeadlessSalesChannel ? this.$tc('sw-seo-url.textSeoUrlsDisallowedForHeadless') : null;
        },

        hasAdditionalSeoSlot() {
            return this.$scopedSlots.hasOwnProperty('seo-additional');
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

    beforeCreate() {
        // register a new module only if doesn't exist
        if (!Shopware.State.list().includes('swSeoUrl')) {
            Shopware.State.registerModule('swSeoUrl', swSeoUrlState);
        }
    },

    created() {
        this.$root.$on('seo-url-save-finish', this.clearDefaultSeoUrls);
        this.createdComponent();
    },

    beforeDestroy() {
        this.$root.$off('seo-url-save-finish', this.clearDefaultSeoUrls);
        Shopware.State.unregisterModule('swSeoUrl');
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
            const salesChannelCriteria = new Criteria();
            salesChannelCriteria.setIds([]);
            salesChannelCriteria.addAssociation('type');

            this.salesChannelRepository.search(salesChannelCriteria).then((salesChannelCollection) => {
                Shopware.State.commit('swSeoUrl/setSalesChannelCollection', salesChannelCollection);
            });
        },

        initSeoUrlCollection() {
            this.showEmptySeoUrlError = false;
            const seoUrlCollection = new EntityCollection(
                this.seoUrlRepository.route,
                this.seoUrlRepository.schema.entity,
                Shopware.Context.api,
                new Criteria(),
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
            Shopware.State.commit('swSeoUrl/setDefaultSeoUrl', defaultSeoUrlEntity);

            this.urls.forEach((entityData) => {
                const entity = this.seoUrlRepository.create();
                Object.assign(entity, entityData);

                seoUrlCollection.add(entity);
            });

            if (!Shopware.State.get('swSeoUrl').defaultSeoUrl) {
                this.showEmptySeoUrlError = true;
            }

            Shopware.State.commit('swSeoUrl/setSeoUrlCollection', seoUrlCollection);
            Shopware.State.commit('swSeoUrl/setOriginalSeoUrls', this.urls);
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
                const seoUrl = this.seoUrlCollection.find((item) => {
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

                Shopware.State.commit('swSeoUrl/setCurrentSeoUrl', entity);

                return;
            }

            Shopware.State.commit('swSeoUrl/setCurrentSeoUrl', currentSeoUrl);
        },
        onSalesChannelChanged(salesChannelId) {
            this.currentSalesChannelId = salesChannelId;
            this.$emit('on-change-sales-channel', salesChannelId);
            this.refreshCurrentSeoUrl();
        },
    },
});
