import { Component, State, Mixin } from 'src/core/shopware';
import CriteriaFactory from 'src/core/factory/criteria.factory';
import mediaMediaGridListener from '../../mixin/mediagrid.listener.mixin';
import mediaSidebarListener from '../../mixin/sibebar.listener.mixin';
import '../../component/sw-media-upload';
import template from './sw-media-catalog.html.twig';
import './sw-media-catalog.less';
import '../../component/sw-media-modal-delete';
import '../../component/sw-media-modal-replace';

Component.register('sw-media-catalog', {
    template,

    mixins: [
        Mixin.getByName('listing'),
        mediaMediaGridListener,
        mediaSidebarListener
    ],

    data() {
        return {
            isLoading: false,
            previewType: 'media-grid-preview-as-grid',
            catalogs: [],
            mediaItems: [],
            lastSelectedItem: null,
            selectionToDelete: null,
            sortType: ['createdAt', 'dsc'],
            catalogIconSize: 200,
            mediaItemToReplace: null
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
            const params = this.getListingParams();

            params.criteria = CriteriaFactory.term('catalogId', this.currentCatalog.id);
            params.sortBy = this.sortType[0];
            params.sortDirection = this.sortType[1];

            return this.mediaItemStore.getList(params).then((response) => {
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
            this.$router.go();
        },

        getCatalogId() {
            return this.$route.params.id;
        },

        getLastSelectedItem() {
            const selection = this.$refs.mediaGrid.selection;

            if (selection.length === 0) {
                this.lastSelectedItem = null;

                return;
            }
            this.lastSelectedItem = selection[selection.length - 1];
        },

        handleMediaGridSelectionRemoved() {
            this.getLastSelectedItem();
        },

        handleMediaGridItemSelected() {
            this.getLastSelectedItem();
        },

        handleMediaGridItemUnselected() {
            this.getLastSelectedItem();
        },

        handleMediaGridItemReplace({ item }) {
            this.mediaItemToReplace = item;
        },

        handleMediaGridItemShowDetails({ item, autoplay }) {
            this.lastSelectedItem = item;
            this.$refs.mediaSidebar.autoplay = autoplay;
            this.$refs.mediaSidebar.showQuickInfo();
        },

        handleSidebarRemoveItem({ item }) {
            this.selectionToDelete = [item];
        },

        handleSidebarRemoveBatchRequest() {
            this.selectionToDelete = this.$refs.mediaGrid.selection;
        },

        handleMediaGridItemDelete({ item }) {
            this.selectionToDelete = [item];
        },

        closeDeleteModal() {
            this.selectionToDelete = null;
        },

        deleteSelection() {
            const mediaItemsDeletion = [];

            this.isLoading = true;

            this.selectionToDelete.forEach((element) => {
                mediaItemsDeletion.push(this.mediaItemStore.getById(element.id).delete(true));
            });

            Promise.all(mediaItemsDeletion).then(() => {
                this.selectionToDelete = null;
                this.getList();
            });
        },

        handleSidebarReplaceItem({ item }) {
            this.mediaItemToReplace = item;
        },

        closeReplaceModal() {
            this.mediaItemToReplace = null;
        },

        handleItemReplaced(replacementPromise) {
            this.closeReplaceModal();

            replacementPromise.then(() => {
                this.getList();
            });
        }
    }
});
