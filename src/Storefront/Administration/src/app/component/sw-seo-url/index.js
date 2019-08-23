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
        this.createdComponent();
    },

    beforeDestroy() {
        this.$store.unregisterModule('swSeoUrl');
    },

    methods: {
        createdComponent() {
            this.initSeoUrlCollection();
            if (!this.showEmptySeoUrlError) {
                this.refreshCurrentSeoUrl();
            }
        },

        initSeoUrlCollection() {
            this.showEmptySeoUrlError = false;
            const seoUrlCollection = new EntityCollection(
                this.seoUrlRepository.route,
                this.seoUrlRepository.schema.entity,
                this.context, new Criteria()
            );

            this.urls.forEach((entityData) => {
                const entity = this.seoUrlRepository.create(this.context);
                Object.assign(entity, entityData);
                entity.isModified = true;
                seoUrlCollection.add(entity);

                // Also save the default seo url(where salesChannel is null) as blueprint for creating new
                if (entity.salesChannelId === null) {
                    this.$store.commit('swSeoUrl/setDefaultSeoUrl', entity);
                }
            });

            if (!this.$store.state.swSeoUrl.defaultSeoUrl) {
                this.showEmptySeoUrlError = true;
            }

            this.$store.commit('swSeoUrl/setSeoUrlCollection', seoUrlCollection);
            this.$store.commit('swSeoUrl/setOriginalSeoUrls', this.urls);
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
