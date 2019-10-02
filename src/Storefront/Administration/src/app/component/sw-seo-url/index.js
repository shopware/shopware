import swSeoUrlState from './state';
import template from './sw-seo-url.html.twig';

const { Component } = Shopware;
const Criteria = Shopware.Data.Criteria;
const EntityCollection = Shopware.Data.EntityCollection;

Component.register('sw-seo-url', {
    template,

    inject: ['context', 'repositoryFactory'],

    mixins: [],

    props: {
        salesChannelId: {
            type: String,
            required: false,
            default: null
        },
        urls: {
            type: Array,
            required: false,
            default() {
                return [];
            }
        },
        isLoading: {
            type: Boolean,
            required: false,
            default: false
        }
    },

    data() {
        return {
            currentSalesChannelId: this.salesChannelId,
            showEmptySeoUrlError: false
        };
    },

    computed: {
        seoUrlCollection() {
            return this.$store.state.swSeoUrl.seoUrlCollection;
        },

        currentSeoUrl() {
            return this.$store.state.swSeoUrl.currentSeoUrl;
        },

        defaultSeoUrl() {
            return this.$store.state.swSeoUrl.defaultSeoUrl;
        },

        seoUrlRepository() {
            return this.repositoryFactory.create('seo_url');
        },

        salesChannelRepository() {
            return this.repositoryFactory.create('sales_channel');
        },

        isHeadlessSalesChannel() {
            if (this.$store.state.swSeoUrl.salesChannelCollection === null) {
                return true;
            }

            const salesChannel = this.$store.state.swSeoUrl.salesChannelCollection.find((entry) => {
                return entry.id === this.currentSalesChannelId;
            });

            // from Defaults.php
            return this.currentSalesChannelId !== null && salesChannel.typeId === 'f183ee5650cf4bdb8a774337575067a6';
        },

        seoUrlHelptext() {
            return this.isHeadlessSalesChannel ? this.$tc('sw-seo-url.textSeoUrlsDisallowedForHeadless') : null;
        }
    },

    watch: {
        urls() {
            this.initSeoUrlCollection();
            this.refreshCurrentSeoUrl();
        }
    },

    beforeCreate() {
        // register a new module only if doesn't exist
        if (!(this.$store && this.$store.state && this.$store.state.swSeoUrl)) {
            this.$store.registerModule('swSeoUrl', swSeoUrlState);
        }
    },

    created() {
        this.$root.$on('seo-url-save-finish', this.clearDefaultSeoUrls);
        this.createdComponent();
    },

    beforeDestroy() {
        this.$root.$off('seo-url-save-finish', this.clearDefaultSeoUrls);
        this.$store.unregisterModule('swSeoUrl');
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

            this.salesChannelRepository.search(salesChannelCriteria, this.context).then((salesChannelCollection) => {
                this.$store.commit('swSeoUrl/setSalesChannelCollection', salesChannelCollection);
            });
        },

        initSeoUrlCollection() {
            this.showEmptySeoUrlError = false;
            const seoUrlCollection = new EntityCollection(
                this.seoUrlRepository.route,
                this.seoUrlRepository.schema.entity,
                this.context, new Criteria()
            );

            const defaultSeoUrlData = this.urls.find((entityData) => {
                return entityData.salesChannelId === null;
            });

            if (defaultSeoUrlData === undefined) {
                this.showEmptySeoUrlError = true;
            }

            const defaultSeoUrlEntity = this.seoUrlRepository.create(this.context);
            Object.assign(defaultSeoUrlEntity, defaultSeoUrlData);
            seoUrlCollection.add(defaultSeoUrlEntity);
            this.$store.commit('swSeoUrl/setDefaultSeoUrl', defaultSeoUrlEntity);

            this.urls.forEach((entityData) => {
                const entity = this.seoUrlRepository.create(this.context);
                Object.assign(entity, entityData);

                seoUrlCollection.add(entity);
            });

            if (!this.$store.state.swSeoUrl.defaultSeoUrl) {
                this.showEmptySeoUrlError = true;
            }

            this.$store.commit('swSeoUrl/setSeoUrlCollection', seoUrlCollection);
            this.$store.commit('swSeoUrl/setOriginalSeoUrls', this.urls);
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
            const actualLanguageId = this.context.languageId;

            const currentSeoUrl = this.seoUrlCollection.find((entity) => {
                return entity.languageId === actualLanguageId && entity.salesChannelId === this.currentSalesChannelId;
            });

            if (!currentSeoUrl) {
                const entity = this.seoUrlRepository.create(this.context);
                entity.foreignKey = this.defaultSeoUrl.foreignKey;
                entity.isCanonical = true;
                entity.languageId = actualLanguageId;
                entity.salesChannelId = this.currentSalesChannelId;
                entity.routeName = this.defaultSeoUrl.routeName;
                entity.pathInfo = this.defaultSeoUrl.pathInfo;
                entity.isModified = true;

                this.seoUrlCollection.add(entity);

                this.$store.commit('swSeoUrl/setCurrentSeoUrl', entity);

                return;
            }

            this.$store.commit('swSeoUrl/setCurrentSeoUrl', currentSeoUrl);
        },
        onSalesChannelChanged(salesChannelId) {
            this.currentSalesChannelId = salesChannelId;
            this.refreshCurrentSeoUrl();
        }
    }
});
