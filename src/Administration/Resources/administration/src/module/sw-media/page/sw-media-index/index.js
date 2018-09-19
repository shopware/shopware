import { Component, State, Mixin } from 'src/core/shopware';
import CriteriaFactory from 'src/core/factory/criteria.factory';
import '../../component/sw-media-modal-delete';
import mediaMediaGridListener from '../../mixin/mediagrid.listener.mixin';
import mediaSidebarListener from '../../mixin/sibebar.listener.mixin';
import template from './sw-media-index.html.twig';
import './sw-media-index.less';

Component.register('sw-media-index', {
    template,

    mixins: [
        mediaMediaGridListener,
        mediaSidebarListener,
        Mixin.getByName('notification')
    ],

    data() {
        return {
            isLoading: false,
            catalogs: [],
            mediaItems: [],
            selectedItems: null,
            selectionToDelete: null,
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

            this.catalogStore.getList({ page: 1, limit: 10 }).then((response) => {
                this.catalogs = response.items;
            });
            this.loadMedia();
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
                this.loadMedia();
            });
            this.selectedItems = null;
        },

        loadMedia() {
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
                criteria: CriteriaFactory.term('id', this.searchId),
                sortBy: 'createdAt',
                sortDirection: 'desc'
            };

            return this.mediaItemStore.getList(params, true).then((response) => {
                this.mediaItems = response.items;
                this.selectedItems = this.mediaItems[0];
                this.handleMediaGridItemShowDetails({ item: this.mediaItems[0] });
            });
        }
    }
});
