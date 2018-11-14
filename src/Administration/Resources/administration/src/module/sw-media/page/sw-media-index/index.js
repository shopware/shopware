import { Component, State, Mixin } from 'src/core/shopware';
import CriteriaFactory from 'src/core/factory/criteria.factory';
import template from './sw-media-index.html.twig';
import './sw-media-index.less';

Component.register('sw-media-index', {
    template,

    mixins: [
        Mixin.getByName('mediagrid-listener')
    ],

    data() {
        return {
            isLoading: false,
            catalogs: [],
            mediaItems: [],
            searchId: this.$route.query.mediaId
        };
    },

    computed: {
        catalogStore() {
            return State.getStore('catalog');
        },

        mediaItemStore() {
            return State.getStore('media');
        },

        isSearch() {
            return this.searchId !== null && this.searchId !== undefined;
        },

        mediaSidebar() {
            return this.$refs.mediaSidebar;
        },

        selectableItems() {
            return this.mediaItems;
        }
    },

    created() {
        this.createComponent();
    },

    beforeRouteUpdate(to, from, next) {
        if (to.query.mediaId) {
            this.searchId = to.query.mediaId;
        } else {
            this.searchId = null;
        }
        this.loadMedia();
        next();
    },

    methods: {
        createComponent() {
            this.isLoading = true;

            this.catalogStore.getList({
                page: 1,
                limit: 10,
                sortBy: 'createdAt',
                sortDirection: 'asc'
            }).then((response) => {
                this.catalogs = response.items;
            });
            this.loadMedia();
        },

        handleMediaGridItemDelete() {
            this.loadMedia();
        },

        showDetails(mediaItem) {
            this._showDetails(mediaItem, false);
        },

        loadMedia() {
            this.isLoading = true;
            this.clearSelection();
            this.mediaItems = [];

            if (this.isSearch) {
                return this.loadSearchedMedia().then(() => {
                    this.isLoading = false;
                });
            }

            return this.loadLastAddedMedia().then(() => {
                this.isLoading = false;
            });
        },

        loadLastAddedMedia() {
            return this.mediaItemStore.getList({
                page: 1,
                limit: 10,
                sortBy: 'createdAt',
                sortDirection: 'desc'
            }, true).then((response) => {
                this.mediaItems = response.items;
            });
        },

        loadSearchedMedia() {
            const params = {
                limit: 1,
                page: 1,
                criteria: CriteriaFactory.equals('id', this.searchId),
                sortBy: 'createdAt',
                sortDirection: 'desc'
            };

            return this.mediaItemStore.getList(params, true).then((response) => {
                if (response.total > 0) {
                    this.mediaItems = response.items;
                    this.handleMediaGridItemShowDetails({ item: this.mediaItems[0] });
                    return;
                }

                this.mediaItems = [];
            });
        }
    }
});
