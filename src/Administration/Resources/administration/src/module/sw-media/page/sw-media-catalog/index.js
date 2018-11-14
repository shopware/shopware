import { Component, State, Mixin } from 'src/core/shopware';
import template from './sw-media-catalog.html.twig';
import './sw-media-catalog.less';

Component.register('sw-media-catalog', {
    template,

    mixins: [
        Mixin.getByName('listing'),
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

        mediaSidebar() {
            return this.$refs.mediaSidebar;
        },

        selectableItems() {
            return this.mediaItems;
        }
    },

    methods: {

        destroyedComponent() {
            this.$root.$off('search', this.onSearch);
        },

        showDetails(mediaItem) {
            this._showDetails(mediaItem, false);
        },

        onNewMedia() {
            this.getList();
        },

        onNewUpload(mediaEntity) {
            this.mediaItems.unshift(mediaEntity);
        },

        getList() {
            this.isLoading = true;
            this.clearSelection();
            const params = this.getListingParams();

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

        getCatalogId() {
            return this.$route.params.id;
        },

        handleMediaGridItemDelete() {
            this.getList();
        }
    }
});
