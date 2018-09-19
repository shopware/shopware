import { Component, State, Mixin } from 'src/core/shopware';
import CriteriaFactory from 'src/core/factory/criteria.factory';
import mediaMediaGridListener from '../../mixin/mediagrid.listener.mixin';
import '../../component/sw-media-upload';
import template from './sw-media-catalog.html.twig';
import './sw-media-catalog.less';

Component.register('sw-media-catalog', {
    template,

    mixins: [
        Mixin.getByName('listing'),
        Mixin.getByName('notification'),
        mediaMediaGridListener
    ],

    data() {
        return {
            isLoading: false,
            previewType: 'media-grid-preview-as-grid',
            catalogs: [],
            mediaItems: [],
            selectedItems: null,
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

        getList() {
            this.isLoading = true;
            this.selectedItems = null;
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

        getSelectedItems() {
            const selection = this.$refs.mediaGrid.selection;

            if (!Array.isArray(selection) || selection.length === 0) {
                this.selectedItems = null;
                return;
            }

            this.selectedItems = selection;
        },

        handleMediaGridSelectionRemoved() {
            this.getSelectedItems();
        },

        handleMediaGridItemSelected() {
            this.getSelectedItems();
        },

        handleMediaGridItemUnselected() {
            this.getSelectedItems();
        },

        handleMediaGridItemShowDetails({ item, autoplay }) {
            this.selectedItems = [item];
            this.$refs.mediaSidebar.autoplay = autoplay;
            this.$refs.mediaSidebar.showQuickInfo();
        },

        handleMediaGridItemDelete() {
            this.getList();
        }
    }
});
