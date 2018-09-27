import { Component, State, Mixin } from 'src/core/shopware';
import CriteriaFactory from 'src/core/factory/criteria.factory';
import '../../component/sw-media-upload';
import template from './sw-media-catalog.html.twig';
import './sw-media-catalog.less';

Component.register('sw-media-catalog', {
    template,

    mixins: [
        Mixin.getByName('listing'),
        Mixin.getByName('notification'),
        Mixin.getByName('mediagrid-listener')
    ],

    data() {
        return {
            isLoading: false,
            previewType: 'media-grid-preview-as-grid',
            catalogs: [],
            mediaItems: [],
            sortType: ['createdAt', 'dsc'],
            catalogIconSize: 200
        };
    },

    computed: {
        mediaItemStore() {
            return State.getStore('media');
        },

        catalogStore() {
            return State.getStore('catalog');
        },

        currentCatalog() {
            return this.catalogStore.getById(this.$route.params.id);
        },

        changeableCatalogs() {
            return this.catalogs.filter((catalog) => {
                return catalog.id !== this.getCatalogId();
            });
        },

        mediaSidebar() {
            return this.$refs.mediaSidebar;
        },

        selectableItems() {
            return this.mediaItems;
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.catalogStore.getList({
                page: 1
            }).then((response) => {
                this.catalogs = response.items;
                this.isLoading = false;
            });
        },

        destroyedComponent() {
            this.$root.$off('search', this.onSearch);
        },

        onNewMedia() {
            this.getList();
        },

        onNewUpload(mediaEntity) {
            mediaEntity.isLoading = true;
            this.mediaItems.unshift(mediaEntity);
        },

        getList() {
            this.isLoading = true;
            this.clearSelection();
            const params = this.getListingParams();

            params.criteria = CriteriaFactory.term('catalogId', this.currentCatalog.id);
            params.sortBy = this.sortType[0];
            params.sortDirection = this.sortType[1];

            return this.mediaItemStore.getList(params, true).then((response) => {
                this.total = response.total;
                this.mediaItems = response.items;
                this.isLoading = false;

                return this.mediaItems;
            });
        },

        sortMediaItems(event) {
            this.sortType = event.split(':');
            this.getList();
        },

        changeCatalog(catalog) {
            this.$router.push({
                name: 'sw.media.catalog-content',
                params: {
                    id: catalog.id
                },
                query: {
                    limit: this.$route.query.limit,
                    page: 1
                }
            });

            this.updateRoute();
            this.getList();
        },

        getCatalogId() {
            return this.$route.params.id;
        },

        handleMediaGridItemDelete() {
            this.getList();
        }
    }
});
