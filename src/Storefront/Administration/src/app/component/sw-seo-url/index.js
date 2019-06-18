import { Component } from 'src/core/shopware';
import Criteria from 'src/core/data-new/criteria.data';
import EntityCollection from 'src/core/data-new/entity-collection.data';
import swSeoUrlState from './state';
import template from './sw-seo-url.html.twig';

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
            currentSalesChannelId: this.salesChannelId
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
        const store = this.$store;

        // register a new module only if doesn't exist
        if (!(store && store.state && store.state.swSeoUrl)) {
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
            this.refreshCurrentSeoUrl();
        },

        initSeoUrlCollection() {
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

            this.$store.commit('swSeoUrl/setSeoUrlCollection', seoUrlCollection);
            this.$store.commit('swSeoUrl/setOriginalSeoUrls', this.urls);
        },

        refreshCurrentSeoUrl() {
            const actualLanguageId = this.context.languageId;

            const currentSeoUrlId = this.seoUrlCollection.find((entity) => {
                return entity.languageId === actualLanguageId && entity.salesChannelId === this.currentSalesChannelId;
            });

            if (!currentSeoUrlId) {
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

            this.$store.commit('swSeoUrl/setCurrentSeoUrl', this.seoUrlCollection.get(currentSeoUrlId));
        },

        onSalesChannelChanged(salesChannelId) {
            this.currentSalesChannelId = salesChannelId;
            this.refreshCurrentSeoUrl();
        }
    }
});
